<?php

namespace App\Http\Requests\Ktms;

use App\Models\Ktms\GameMatch;
use App\Models\Ktms\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class MatchRequest extends FormRequest
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
        return [
            'home_team_id' => ['required', 'integer', 'exists:ktms_teams,id', 'different:away_team_id'],
            'away_team_id' => ['required', 'integer', 'exists:ktms_teams,id', 'different:home_team_id'],
            'match_number' => ['nullable', 'integer', 'min:1'],
            'round' => ['nullable', 'string', 'max:100'],
            'venue' => ['nullable', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(array_keys(GameMatch::STATUSES))],
            'half_duration_seconds' => ['required', 'integer', 'min:60', 'max:3600'],
            'raid_duration_seconds' => ['required', 'integer', 'min:10', 'max:120'],
            'players_per_side' => ['required', 'integer', 'min:5', 'max:12'],
        ];
    }

    /** Both sides have to be squads registered in the tournament hosting the match. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tournament = $this->route('tournament') ?? $this->route('match')?->tournament;

            if (! $tournament) {
                return;
            }

            foreach (['home_team_id', 'away_team_id'] as $field) {
                $teamId = $this->input($field);

                if (! $teamId) {
                    continue;
                }

                $belongs = Team::where('id', $teamId)
                    ->where('tournament_id', $tournament->id)
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add($field, 'That squad is not registered in this tournament.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'home_team_id' => 'home team',
            'away_team_id' => 'away team',
            'half_duration_seconds' => 'half duration',
            'raid_duration_seconds' => 'raid duration',
            'players_per_side' => 'players per side',
        ];
    }
}
