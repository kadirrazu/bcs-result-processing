<div class="row g-3">
 <div class="col-md-3"><label class="form-label required">Cadre code</label><input type="number" name="cadre_code" class="form-control" value="{{ old('cadre_code',$record->cadre_code ?? '') }}" required></div>
 <div class="col-md-3"><label class="form-label required">Abbreviation</label><input name="cadre_abbr" class="form-control" value="{{ old('cadre_abbr',$record->cadre_abbr ?? '') }}" maxlength="20" required></div>
 <div class="col-md-3"><label class="form-label required">Type</label><select name="cadre_type" class="form-select" required>@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('cadre_type',$record->cadre_type->value ?? '')===$type->value)>{{ $type->value }} — {{ $type->label() }}</option>@endforeach</select></div>
 <div class="col-md-3"><label class="form-label required">Display order</label><input type="number" min="0" name="display_order" class="form-control" value="{{ old('display_order',$record->display_order ?? 0) }}" required></div>
 <div class="col-md-6"><label class="form-label required">Cadre name</label><input name="cadre_name" class="form-control" value="{{ old('cadre_name',$record->cadre_name ?? '') }}" required></div>
 <div class="col-md-6"><label class="form-label required">ক্যাডারের নাম</label><input name="cadre_name_bn" class="form-control" value="{{ old('cadre_name_bn',$record->cadre_name_bn ?? '') }}" required></div>
 <div class="col-md-6"><label class="form-label">Default / main post name</label><input name="post_name" class="form-control" value="{{ old('post_name',$record->post_name ?? '') }}"></div>
 <div class="col-md-6"><label class="form-label">পদের নাম</label><input name="post_name_bn" class="form-control" value="{{ old('post_name_bn',$record->post_name_bn ?? '') }}"></div>
 <div class="col-12"><label class="form-check form-switch"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active',$record->is_active ?? true))><span class="form-check-label">Active</span></label></div>
 @if(isset($record) && $record)
 <div class="col-12"><label class="form-label required">Reason for correction</label><textarea name="correction_reason" class="form-control @error('correction_reason') is-invalid @enderror" rows="3" required placeholder="State why this master-data change is required.">{{ old('correction_reason') }}</textarea>@error('correction_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
 @endif
</div>
