<?php

namespace App\Http\Requests\Ktms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $tournamentId = $team?->tournament_id ?? $this->route('tournament')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Squad names only need to be unique inside their own tournament.
                Rule::unique('ktms_teams', 'name')
                    ->where(fn ($query) => $query->where('tournament_id', $tournamentId))
                    ->ignore($team?->id),
            ],
            'short_name' => ['nullable', 'string', 'max:10'],
            // No SVG: this project's ImageUpload re-encodes through GD, which
            // only handles raster images, and an SVG can carry script.
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'coach_name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Another squad in this tournament already uses that name.',
            'primary_color.regex' => 'Pick a colour in #rrggbb format.',
            'secondary_color.regex' => 'Pick a colour in #rrggbb format.',
        ];
    }
}
