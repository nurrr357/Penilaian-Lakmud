<form method="get" class="lak-filter-bar table-filter">
@if($event && $page!=='kegiatan')<input type="hidden" name="event_id" value="{{ $event->id }}">@endif
@if(request('material_id'))<input type="hidden" name="material_id" value="{{ request('material_id') }}">@endif
<div class="lak-field"><label for="q">Cari nama</label><input class="lak-input" id="q" type="search" name="q" value="{{ $q }}" maxlength="200"></div>
<div class="lak-field"><label for="per_page">Baris per halaman</label><select class="lak-input" id="per_page" name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($listing->perPage()===$size)>{{ $size }}</option>@endforeach</select></div>
<button type="submit" class="lak-btn lak-btn--outlined">Terapkan</button>
</form>
