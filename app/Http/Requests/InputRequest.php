<?php

namespace App\Http\Requests;

use App\Services\PanelPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PanelPermissions::INPUTS[$this->route('kind')] ?? '') ?? false;
    }

    public function rules(): array
    {
        $fields = match ($this->route('kind')) {
            'absensi' => ['attendance'], 'pretest' => ['pretest'], 'posttest' => ['posttest'], 'keaktifan' => ['activity'], 'etika' => ['ethics'], 'pemahaman' => ['understanding'], default => []
        };
        abort_if(! $fields, 404);
        $rules = ['material_id' => ['required', 'integer', Rule::exists('event_materials', 'id')->where('lakmud_event_id', $this->route('event'))->where('is_active', true)], 'rows' => ['required', 'array', 'min:1'], 'rows.*' => ['array:'.implode(',', $fields)]];
        foreach ($fields as $field) {
            $rules['rows.*.'.$field] = $field === 'attendance' ? ['nullable', Rule::in(['present', 'absent'])] : ['nullable', 'numeric', 'between:10,20', 'regex:/^\d{1,2}(\.\d{1,2})?$/'];
        }

        return $rules;
    }
}
