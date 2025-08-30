@extends('layouts.app')

@section('content')
<h1>大会を編集</h1>

<form action="{{ route('tournaments.update', $tournament->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <table border="1" cellpadding="8" cellspacing="0" class="table table-bordered table-striped">
        <tr><th>大会名</th>
            <td><input type="text" name="name" value="{{ old('name', $tournament->name) }}" class="form-control"></td></tr>

    <tr>
    <th>開始日</th>
        <td>
            <input type="date" name="start_date"
                value="{{ old('start_date', optional($tournament->start_date)->format('Y-m-d')) }}"
                class="form-control">
            @error('start_date')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </td>
    </tr>
    <tr>
    <th>終了日</th>
         <td>
            <input type="date" name="end_date"
                value="{{ old('end_date', optional($tournament->end_date)->format('Y-m-d')) }}"
                class="form-control">
            @error('end_date')
                 <div class="text-danger">{{ $message }}</div>
            @enderror
        </td>
    </tr>
    <tr>
    <th>申込開始日</th>
        <td>
            <input type="date" name="entry_start"
                value="{{ old('entry_start', optional($tournament->entry_start)->format('Y-m-d')) }}"
                class="form-control">
            @error('entry_start')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </td>
    </tr>
    <tr>
        <th>申込締切日</th>
        <td>
            <input type="date" name="entry_end"
                value="{{ old('entry_end', optional($tournament->entry_end)->format('Y-m-d')) }}"
                class="form-control">
            @error('entry_end')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </td>
    </tr>

    <tr>
        <th>種別</th>
            <td>
                <select name="gender" required>
                    <option value="M" @selected(old('gender',$tournament->gender) === 'M')>男子</option>
                    <option value="F" @selected(old('gender',$tournament->gender) === 'F')>女子</option>
                    <option value="X" @selected(old('gender',$tournament->gender) === 'X')>男女/未設定</option>
                </select>
            </td>
        </tr>
        <tr>
            <th>大会区分</th>
            <td>
                <select name="official_type" required>
                    <option value="official" @selected(old('official_type',$tournament->official_type) === 'official')>公認</option>
                    <option value="approved" @selected(old('official_type',$tournament->official_type) === 'approved')>承認</option>
                    <option value="other" @selected(old('official_type',$tournament->official_type) === 'other')>その他</option>
                </select>
            </td>
        </tr>

        <tr>
        <th>検量証必須</th>
        <td>
            <input type="hidden" name="inspection_required" value="0">
            <input type="checkbox" name="inspection_required" value="1"
                @checked(old('inspection_required', $tournament->inspection_required))>
            <small class="text-muted ms-2">※ チェック時：検量証未入力のボールは仮登録扱い</small>
        </td>
        </tr>

        <tr><th>会場名</th>
            <td><input type="text" name="venue_name" value="{{ old('venue_name', $tournament->venue_name) }}" class="form-control"></td></tr>

        <tr><th>会場住所</th>
            <td><input type="text" name="venue_address" value="{{ old('venue_address', $tournament->venue_address) }}" class="form-control"></td></tr>

        <tr><th>電話番号</th>
            <td><input type="text" name="venue_tel" value="{{ old('venue_tel', $tournament->venue_tel) }}" class="form-control"></td></tr>

        <tr><th>FAX</th>
            <td><input type="text" name="venue_fax" value="{{ old('venue_fax', $tournament->venue_fax) }}" class="form-control"></td></tr>

        <tr><th>主催</th>
            <td><input type="text" name="host" value="{{ old('host', $tournament->host) }}" class="form-control"></td></tr>

        <tr><th>特別協賛</th>
            <td><input type="text" name="special_sponsor" value="{{ old('special_sponsor', $tournament->special_sponsor) }}" class="form-control"></td></tr>

        <tr><th>後援</th>
            <td><input type="text" name="support" value="{{ old('support', $tournament->support) }}" class="form-control"></td></tr>

        <tr><th>協賛</th>
            <td><input type="text" name="sponsor" value="{{ old('sponsor', $tournament->sponsor) }}" class="form-control"></td></tr>

        <tr><th>主管</th>
            <td><input type="text" name="supervisor" value="{{ old('supervisor', $tournament->supervisor) }}" class="form-control"></td></tr>

        <tr><th>公認</th>
            <td><input type="text" name="authorized_by" value="{{ old('authorized_by', $tournament->authorized_by) }}" class="form-control"></td></tr>

        <tr><th>TV放映</th>
            <td><input type="text" name="broadcast" value="{{ old('broadcast', $tournament->broadcast) }}" class="form-control"></td></tr>

        <tr><th>配信</th>
            <td><input type="text" name="streaming" value="{{ old('streaming', $tournament->streaming) }}" class="form-control"></td></tr>

        <tr><th>賞金</th>
            <td><input type="text" name="prize" value="{{ old('prize', $tournament->prize) }}" class="form-control"></td></tr>

        <tr><th>観客数</th>
            <td><input type="text" name="audience" value="{{ old('audience', $tournament->audience) }}" class="form-control"></td></tr>

        <tr><th>参加条件</th>
            <td><textarea name="entry_conditions" class="form-control">{{ old('entry_conditions', $tournament->entry_conditions) }}</textarea></td></tr>

        <tr><th>資料</th>
            <td><textarea name="materials" class="form-control">{{ old('materials', $tournament->materials) }}</textarea></td></tr>

        <tr><th>前年大会</th>
            <td><input type="text" name="previous_event" value="{{ old('previous_event', $tournament->previous_event) }}" class="form-control"></td></tr>

        <tr><th>ポスター画像</th>
            <td><input type="file" name="image" class="form-control"></td></tr>
    </table>

    <button type="submit" class="btn btn-primary">更新</button>
    <a href="{{ route('tournaments.index') }}" class="btn btn-danger">キャンセル</a>
    
</form>
@endsection
