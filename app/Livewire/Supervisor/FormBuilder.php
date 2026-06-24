<?php

namespace App\Livewire\Supervisor;

use App\Models\Form;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Livewire\Component;
use Livewire\WithFileUploads;

class FormBuilder extends Component
{
    use WithFileUploads;

    public ?int $formId = null;

    public bool $isEditing = false;

    // Form settings
    public string $title = '';

    public ?string $description = null;

    public string $color = '#7a2727';

    public string $slug = '';

    public $header_image_file = null;

    public ?string $header_image_path = null;

    public ?string $policy_text = null;

    public ?string $success_text = null;

    // Fields
    public array $fields = [];

    public function mount(?int $formId = null): void
    {
        if ($formId) {
            $this->formId = $formId;
            $this->isEditing = true;

            $supervisorId = auth()->guard('supervisor')->id();
            $form = Form::where('supervisor_id', $supervisorId)->findOrFail($formId);

            $this->title = $form->title;
            $this->description = $form->description;
            $this->color = $form->color;
            $this->slug = $form->slug;
            $this->header_image_path = $form->header_image_path;
            $this->policy_text = $form->policy_text;
            $this->success_text = $form->success_text;
            $this->fields = $form->fields ?? [];
        } else {
            $this->slug = Str::random(8);
            // Default first field
            $this->addField('text', 'الاسم الكامل', true, true, false);
        }
    }

    public function addField(string $type = 'text', string $label = '', bool $required = false, bool $isName = false, bool $isUsername = false): void
    {
        $this->fields[] = [
            'id' => 'field_'.uniqid(),
            'type' => $type,
            'label' => $label,
            'required' => $required,
            'options' => [],
            'is_student_name' => $isName,
            'is_student_username' => $isUsername,
        ];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function moveUp(int $index): void
    {
        if ($index > 0) {
            $temp = $this->fields[$index - 1];
            $this->fields[$index - 1] = $this->fields[$index];
            $this->fields[$index] = $temp;
        }
    }

    public function moveDown(int $index): void
    {
        if ($index < count($this->fields) - 1) {
            $temp = $this->fields[$index + 1];
            $this->fields[$index + 1] = $this->fields[$index];
            $this->fields[$index] = $temp;
        }
    }

    public function addOption(int $fieldIndex): void
    {
        $this->fields[$fieldIndex]['options'][] = '';
    }

    public function removeOption(int $fieldIndex, int $optionIndex): void
    {
        unset($this->fields[$fieldIndex]['options'][$optionIndex]);
        $this->fields[$fieldIndex]['options'] = array_values($this->fields[$fieldIndex]['options']);
    }

    public function toggleNameDesignation(int $index): void
    {
        $currentState = $this->fields[$index]['is_student_name'] ?? false;

        // Uncheck all other name designations
        foreach ($this->fields as $i => $field) {
            $this->fields[$i]['is_student_name'] = false;
        }

        // Set current designation to toggled state
        $this->fields[$index]['is_student_name'] = ! $currentState;
    }

    public function toggleUsernameDesignation(int $index): void
    {
        $currentState = $this->fields[$index]['is_student_username'] ?? false;

        // Uncheck all other username designations
        foreach ($this->fields as $i => $field) {
            $this->fields[$i]['is_student_username'] = false;
        }

        // Set current designation to toggled state
        $this->fields[$index]['is_student_username'] = ! $currentState;
    }

    public function save(): void
    {
        $this->slug = Str::slug($this->slug);

        $this->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'required|string|max:7',
            'slug' => 'required|string|alpha_dash|unique:forms,slug,'.$this->formId,
            'header_image_file' => 'nullable|image|max:5120', // 5MB max
            'policy_text' => 'nullable|string',
            'success_text' => 'nullable|string',
            'fields' => 'required|array|min:1',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.type' => 'required|in:text,image,select,multiselect,date',
            'fields.*.allow_other' => 'nullable|boolean',
        ], [
            'fields.*.label.required' => 'يجب إدخال تسمية للحقل.',
            'slug.unique' => 'رابط الاستمارة مستخدم بالفعل، يرجى كتابة رابط آخر.',
            'slug.alpha_dash' => 'يجب أن يحتوي الرابط على أحرف صغيرة وأرقام وشُرط فقط.',
        ]);

        $supervisorId = auth()->guard('supervisor')->id();

        // Process header image if uploaded
        if ($this->header_image_file) {
            $manager = new ImageManager(new Driver);
            $tempPath = $this->header_image_file->getRealPath();

            // Read image and scale if too wide
            $image = $manager->decode($tempPath);
            if ($image->width() > 1200) {
                $image->scale(width: 1200);
            }

            // Encode as WebP
            $webpData = $image->encode(new WebpEncoder(80))->toString();
            $filename = 'form_headers/'.uniqid().'_header.webp';
            Storage::disk('public')->put($filename, $webpData);

            // Delete old file if exists
            if ($this->header_image_path) {
                Storage::disk('public')->delete($this->header_image_path);
            }

            $this->header_image_path = $filename;
        }

        // Save Form
        Form::updateOrCreate(
            ['id' => $this->formId, 'supervisor_id' => $supervisorId],
            [
                'supervisor_id' => $supervisorId,
                'title' => $this->title,
                'description' => $this->description,
                'color' => $this->color,
                'slug' => $this->slug,
                'header_image_path' => $this->header_image_path,
                'policy_text' => $this->policy_text,
                'success_text' => $this->success_text,
                'fields' => $this->fields,
            ]
        );

        Flux::toast($this->isEditing ? 'تم تعديل النموذج بنجاح' : 'تم حفظ النموذج بنجاح', variant: 'success');
        $this->redirectRoute('supervisor.forms');
    }

    public function render()
    {
        return view('livewire.supervisor.form-builder');
    }
}
