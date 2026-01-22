@php
  // 用語：パーシャル（部分テンプレート。複数画面で共通利用する小さなBladeファイル）
@endphp
<div class="row g-3">
  <div class="col-md-8">
    <label class="form-label">会場名 <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $venue->name) }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label class="form-label">郵便番号</label>
    <div class="input-group">
      <span class="input-group-text">〒</span>
      <input type="text" name="postal_code" id="postal_code" class="form-control @error('postal_code') is-invalid @enderror"
             value="{{ old('postal_code', $venue->postal_code) }}" placeholder="101-0047" inputmode="numeric">
      <button class="btn btn-outline-secondary" type="button" id="zip_lookup_btn">住所自動入力</button>
    </div>
    <small class="text-muted">
      （郵便番号API：郵便番号から都道府県・市区町村を返すWebサービス。入力後に自動補完します）
    </small>
    @error('postal_code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-8">
    <label class="form-label">住所</label>
    <input type="text" name="address" id="address" class="form-control @error('address') is-invalid @enderror"
           value="{{ old('address', $venue->address) }}" placeholder="例：東京都千代田区…">
    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label class="form-label">公式サイトURL</label>
    <input type="url" name="website_url" class="form-control @error('website_url') is-invalid @enderror"
           value="{{ old('website_url', $venue->website_url) }}" placeholder="https://example.com">
    @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label class="form-label">電話番号</label>
    <input type="text" name="tel" class="form-control @error('tel') is-invalid @enderror"
           value="{{ old('tel', $venue->tel) }}" placeholder="03-1234-5678">
    @error('tel')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-md-4">
    <label class="form-label">FAX</label>
    <input type="text" name="fax" class="form-control @error('fax') is-invalid @enderror"
           value="{{ old('fax', $venue->fax) }}" placeholder="03-1234-5679">
    @error('fax')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>

  <div class="col-12">
    <label class="form-label">会場データ</label>
    <textarea name="note" rows="3" class="form-control @error('note') is-invalid @enderror"
              placeholder="駐車場、レーン数、レーンメンテ情報など">{{ old('note', $venue->note) }}</textarea>
    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
</div>

@push('scripts')
<script>
// 郵便番号→住所自動補完（zipcloud API）
(() => {
  const $ = (s) => document.querySelector(s);
  const btn  = $('#zip_lookup_btn');
  const input= $('#postal_code');
  const addr = $('#address');

  async function lookup() {
    const raw = (input.value || '').replace(/\D/g,''); // 数字以外除去
    if (raw.length !== 7) { return; }
    const url = `https://zipcloud.ibsnet.co.jp/api/search?zipcode=${raw}`;
    try {
      const res = await fetch(url);
      const json = await res.json();
      if (json && json.results && json.results.length) {
        const r = json.results[0];
        // 例：東京都千代田区内神田
        const partial = `${r.address1}${r.address2}${r.address3}`;
        // 既存の住所が空ならそのまま、入っていたら先頭に補完を残して後半は編集可にする
        if (!addr.value) {
          addr.value = partial;
        } else if (!addr.value.startsWith(partial)) {
          addr.value = `${partial}${addr.value}`;
        }
        // 郵便番号はハイフン付に整える
        input.value = `${raw.slice(0,3)}-${raw.slice(3)}`;
      }
    } catch (e) {
      console.warn('ZIP lookup failed', e);
    }
  }

  if (btn) btn.addEventListener('click', lookup);
  if (input) input.addEventListener('blur', lookup);
})();
</script>
@endpush
