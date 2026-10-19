<form method="get" class="filter table-filter">
@if($event && $page!=='kegiatan')<input type="hidden" name="event_id" value="{{ $event->id }}">@endif
@if(request('material_id'))<input type="hidden" name="material_id" value="{{ request('material_id') }}">@endif
<label>Cari nama<input type="search" name="q" value="{{ $q }}" maxlength="200"></label>
<label>Baris per halaman<select name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected($listing->perPage()===$size)>{{ $size }}</option>@endforeach</select></label>
<button class="secondary">Terapkan</button>
</form>
