<?php

namespace App\Ai\Tools;

use App\Models\Stage;
use App\Models\Student;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class getOrganizationStructure implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the academy structure: every stage (مرحلة) with its supervisors, and every circle (حلقة) within it with its teachers and student counts by status. '
            .'Call this first when a question involves stages, circles, who supervises or teaches where, or how students are distributed. '
            .'It also gives you the exact stage and circle names to pass as filters to the other tools.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $studentCounts = Student::query()
            ->selectRaw('circle_id, status, count(*) as total')
            ->groupBy('circle_id', 'status')
            ->get()
            ->groupBy('circle_id');

        $stages = Stage::with([
            'supervisors:id,name',
            'circles:id,name,description,stage_id',
            'circles.teachers:id,name',
        ])->get();

        $structure = $stages->map(fn (Stage $stage) => [
            'stage' => $stage->name,
            'description' => $stage->description,
            'supervisors' => $stage->supervisors->pluck('name')->all(),
            'circles' => $stage->circles->map(fn ($circle) => [
                'circle' => $circle->name,
                'description' => $circle->description,
                'teachers' => $circle->teachers->pluck('name')->all(),
                'students' => $this->countsByStatus($studentCounts->get($circle->id)),
            ])->all(),
        ]);

        return json_encode([
            'stages' => $structure->all(),
            'students_without_circle' => Student::whereNull('circle_id')->count(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  Collection<int, object>|null  $rows
     * @return array<string, int>
     */
    private function countsByStatus(mixed $rows): array
    {
        $counts = [
            'total' => 0,
            'مشارك' => 0,
            'تحت التسجيل' => 0,
            'موقوف' => 0,
            'غادر الحلقات' => 0,
        ];

        foreach ($rows ?? [] as $row) {
            $counts['total'] += $row->total;

            $label = match ($row->status) {
                'active' => 'مشارك',
                'registering' => 'تحت التسجيل',
                'suspended' => 'موقوف',
                'left' => 'غادر الحلقات',
                default => null,
            };

            if ($label !== null) {
                $counts[$label] += $row->total;
            }
        }

        return $counts;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
