@php($definition = $definition ?? null)
<div class="row g-2">
    <div class="col-lg-4">
        <label class="form-label">大会</label>
        <select name="tournament_id" class="form-select" required>
            <option value="">選択してください</option>
            @foreach ($tournaments as $tournament)
                <option value="{{ $tournament->id }}" @selected(old('tournament_id', $definition?->tournament_id ?? $tournamentId) == $tournament->id)>
                    {{ optional($tournament->start_date)->format('Y/m/d') }} {{ $tournament->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-2">
        <label class="form-label">ラウンド</label>
        <input type="text" name="stage" value="{{ old('stage', $definition?->stage) }}" class="form-control" required>
    </div>
    <div class="col-lg-2">
        <label class="form-label">シフト</label>
        <input type="text" name="shift" value="{{ old('shift', $definition?->shift) }}" class="form-control">
    </div>
    <div class="col-lg-2">
        <label class="form-label">性別</label>
        <select name="gender" class="form-select">
            <option value="">共通</option>
            <option value="M" @selected(old('gender', $definition?->gender) === 'M')>男子</option>
            <option value="F" @selected(old('gender', $definition?->gender) === 'F')>女子</option>
        </select>
    </div>
    <div class="col-lg-2">
        <label class="form-label">シリーズ名</label>
        <input type="text" name="label" value="{{ old('label', $definition?->label) }}" class="form-control" required placeholder="予選第1シリーズ">
    </div>
    <div class="col-lg-2">
        <label class="form-label">開始G</label>
        <input type="number" name="start_game" min="1" value="{{ old('start_game', $definition?->start_game) }}" class="form-control" required>
    </div>
    <div class="col-lg-2">
        <label class="form-label">終了G</label>
        <input type="number" name="end_game" min="1" value="{{ old('end_game', $definition?->end_game) }}" class="form-control" required>
    </div>
    <div class="col-lg-3 d-flex align-items-end gap-3">
        <div class="form-check mb-2">
            <input type="hidden" name="is_800_eligible" value="0">
            <input type="checkbox" name="is_800_eligible" value="1" class="form-check-input" @checked(old('is_800_eligible', $definition?->is_800_eligible ?? true))>
            <label class="form-check-label">800判定対象</label>
        </div>
        <div class="form-check mb-2">
            <input type="hidden" name="is_enabled" value="0">
            <input type="checkbox" name="is_enabled" value="1" class="form-check-input" @checked(old('is_enabled', $definition?->is_enabled ?? true))>
            <label class="form-check-label">有効</label>
        </div>
    </div>
</div>
