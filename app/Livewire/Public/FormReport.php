<?php

namespace App\Livewire\Public;

use App\Models\Form;
use App\Models\FormResponse;
use Livewire\Attributes\Url;
use Livewire\Component;

class FormReport extends Component
{
    public string $slug;

    public Form $form;

    #[Url(keep: false)]
    public string $groupBy = '';

    #[Url(keep: false)]
    public string $subGroupBy = '';

    #[Url(keep: false)]
    public array $filters = [];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->form = Form::where('slug', $slug)->firstOrFail();

        if (! $this->form->is_public_report) {
            abort(403, 'هذا التقرير غير متاح للعامة.');
        }

        // Initialize filters array if not set
        foreach ($this->form->fields as $field) {
            $fieldId = $field['id'];
            if (! isset($this->filters[$fieldId])) {
                $this->filters[$fieldId] = $field['type'] === 'multiselect' ? [] : '';
            }
        }
    }

    public function resetFilters(): void
    {
        $this->groupBy = '';
        $this->subGroupBy = '';
        $this->filters = [];
        foreach ($this->form->fields as $field) {
            $fieldId = $field['id'];
            $this->filters[$fieldId] = $field['type'] === 'multiselect' ? [] : '';
        }
    }

    public function render()
    {
        $responses = FormResponse::where('form_id', $this->form->id)
            ->latest()
            ->get();

        // Apply filters
        $filteredResponses = $responses->filter(function ($response) {
            foreach ($this->form->fields as $field) {
                $fieldId = $field['id'];
                $fieldType = $field['type'];

                $filterVal = $this->filters[$fieldId] ?? null;
                if ($filterVal === null || $filterVal === '' || (is_array($filterVal) && empty($filterVal))) {
                    continue;
                }

                $answer = $response->answers[$fieldId] ?? null;

                if ($fieldType === 'text') {
                    if ($answer === null || mb_stripos($answer, $filterVal) === false) {
                        return false;
                    }
                } elseif ($fieldType === 'select') {
                    if (is_array($filterVal)) {
                        if (! in_array($answer, $filterVal)) {
                            return false;
                        }
                    } else {
                        if ($answer !== $filterVal) {
                            return false;
                        }
                    }
                } elseif ($fieldType === 'multiselect') {
                    if (! is_array($answer)) {
                        return false;
                    }
                    if (is_array($filterVal)) {
                        if (empty(array_intersect($answer, $filterVal))) {
                            return false;
                        }
                    } else {
                        if (! in_array($filterVal, $answer)) {
                            return false;
                        }
                    }
                } elseif ($fieldType === 'date') {
                    if ($answer === null || mb_stripos($answer, $filterVal) === false) {
                        return false;
                    }
                }
            }

            return true;
        });

        // Group helper function
        $getGroupKey = function ($response, $fieldId) {
            $val = $response->answers[$fieldId] ?? null;
            if ($val === null || $val === '') {
                return 'غير محدد';
            }
            if (is_array($val)) {
                return implode(', ', $val);
            }

            return (string) $val;
        };

        // Structuring grouped data
        $groupedData = [];
        $hasGrouping = ! empty($this->groupBy);

        if ($hasGrouping) {
            $primaryGroups = $filteredResponses->groupBy(function ($response) use ($getGroupKey) {
                return $getGroupKey($response, $this->groupBy);
            });

            if (! empty($this->subGroupBy) && $this->subGroupBy !== $this->groupBy) {
                foreach ($primaryGroups as $primaryKey => $subResponses) {
                    $groupedData[$primaryKey] = $subResponses->groupBy(function ($response) use ($getGroupKey) {
                        return $getGroupKey($response, $this->subGroupBy);
                    });
                }
            } else {
                $groupedData = $primaryGroups;
            }
        } else {
            $groupedData = $filteredResponses;
        }

        return view('livewire.public.form-report', [
            'groupedData' => $groupedData,
            'hasGrouping' => $hasGrouping,
            'hasSubGrouping' => $hasGrouping && ! empty($this->subGroupBy) && $this->subGroupBy !== $this->groupBy,
        ])->layout('layouts.blank');
    }
}
