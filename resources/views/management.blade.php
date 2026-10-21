@php
    $permission = match($page) {'kegiatan'=>'events.manage','materi'=>'materials.manage',default=>'participants.manage'};
    $canManage = auth()->user()->can($permission);
    $canAdd = $page === 'kegiatan' || ($event && !$event->finalized);
    $columns = $page === 'kegiatan' ? 6 : ($page === 'materi' ? 5 : 4);
@endphp
<section class="lak-card">
@if($page !== 'kegiatan')
    <div class="lak-card__head"><div><h2 class="lak-card__title">{{ $event?->name ?? 'Belum ada kegiatan' }}</h2>
    <p class="subtitle">{{ $page === 'materi' ? 'Urutan 1 sampai 15 harus unik. Hanya materi aktif dihitung.' : 'Nomor urut peserta harus unik di kegiatan ini.' }}</p></div>
    @if($canManage && $canAdd)<button type="button" class="lak-btn lak-btn--filled" data-modal-open="create"><md-icon aria-hidden="true">add</md-icon>Tambah {{ $page }}</button>@endif</div>
@elseif($canManage && $canAdd)
    <div class="lak-card__head"><h2 class="lak-card__title">Daftar kegiatan</h2><button type="button" class="lak-btn lak-btn--filled" data-modal-open="create"><md-icon aria-hidden="true">add</md-icon>Tambah {{ $page }}</button></div>
@endif
<div class="table-wrap" tabindex="0" role="region" aria-label="Tabel {{ $page }}">
<table class="lak-table"><thead><tr><th>No.</th><th>Nama {{ $page }}</th>@if($page==='kegiatan')<th>Organisasi / tempat</th><th>Tanggal</th><th>Status</th>@else<th>Nomor urut</th>@if($page==='materi')<th>Status</th>@endif @endif<th>Aksi</th></tr></thead><tbody>
@forelse($listing as $record)
@php
    $locked = $page==='kegiatan' ? $record->finalized : $event->finalized;
@endphp
<tr><td>{{ $listing->firstItem()+$loop->index }}</td><th scope="row">{{ $record->name }}</th>
@if($page==='kegiatan')<td class="wrap">{{ $record->organization }}<br>{{ $record->venue }}</td><td>{{ $record->start_date }} sampai {{ $record->end_date }}</td><td><span class="lak-badge lak-badge--{{ $locked ? 'finalized' : 'active' }}">{{ $locked ? 'Dikunci' : 'Terbuka' }}</span></td>
@else<td>{{ $record->sequence }}</td>@if($page==='materi')<td><span class="lak-badge lak-badge--{{ $record->is_active ? 'active' : 'draft' }}">{{ $record->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>@endif @endif
<td><div class="lak-form__row">
@if($canManage)<button type="button" class="lak-btn lak-btn--outlined lak-btn--sm" @if(!$locked) data-modal-open="edit-{{ $record->id }}" @endif @disabled($locked)><md-icon aria-hidden="true">edit</md-icon>Ubah</button>
<form method="post" action="{{ $page==='kegiatan' ? '/kegiatan/'.$record->id : '/kegiatan/'.$event->id.'/'.$page.'/'.$record->id }}" data-confirm="Hapus {{ $record->name }} beserta input terkait?">@csrf @method('DELETE')<button type="submit" class="lak-btn lak-btn--danger lak-btn--sm" @disabled($locked)><md-icon aria-hidden="true">delete</md-icon>Hapus</button></form>@else<span class="lak-muted">Hanya baca</span>@endif
</div></td></tr>
@empty<tr><td colspan="{{ $columns }}">{{ $q !== '' ? 'Tidak ada data sesuai pencarian. Ubah kata pencarian.' : 'Belum ada data. Tambahkan data jika Anda memiliki izin.' }}</td></tr>@endforelse
</tbody></table></div>
</section>
@if($canManage)
@foreach(collect([null])->concat($listing->items()) as $record)
@php
    $modalId = $record ? 'edit-'.$record->id : 'create';
    $failed = $errors->any() && old('_modal') === $modalId;
    $locked = $record && $page==='kegiatan' ? $record->finalized : ($page!=='kegiatan' && (!$event || $event->finalized));
@endphp
@if(!$locked)
<dialog id="{{ $modalId }}" class="crud-modal" aria-labelledby="{{ $modalId }}-title" @if($failed) data-reopen @endif>
<form method="post" action="{{ $page==='kegiatan' ? '/kegiatan'.($record ? '/'.$record->id : '') : '/kegiatan/'.$event->id.'/'.$page.($record ? '/'.$record->id : '') }}">
@csrf @include('table-state') @if($record) @method('PUT') @endif
<input type="hidden" name="_modal" value="{{ $modalId }}">
<div class="crud-modal__head"><h2 id="{{ $modalId }}-title">{{ $record ? 'Ubah' : 'Tambah' }} {{ $page }}</h2></div>
<div class="crud-modal__body">
@if($page!=='kegiatan')<p class="lak-modal__meta">Kegiatan: <strong>{{ $event->name }}</strong></p>@endif
@if($failed)<div class="lak-alert lak-alert--error" role="alert"><p class="lak-alert__head">Data belum disimpan.</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@php($fields = $page==='kegiatan' ? ['name'=>'Nama kegiatan','organization'=>'Organisasi','venue'=>'Tempat','start_date'=>'Tanggal mulai','end_date'=>'Tanggal selesai'] : ['name'=>'Nama '.$page,'sequence'=>'Nomor urut'])
<div class="lak-form__grid">@foreach($fields as $field=>$label)
<div class="lak-field"><label for="{{ $modalId }}-{{ $field }}">{{ $label }}</label>
<input class="lak-input" id="{{ $modalId }}-{{ $field }}" name="{{ $field }}" type="{{ str_contains($field,'date') ? 'date' : ($field==='sequence' ? 'number' : 'text') }}" value="{{ $failed ? old($field) : $record?->$field }}" @if($field==='sequence') min="1" max="{{ $page==='materi' ? 15 : 99999 }}" @else maxlength="200" @endif required @if($failed && $errors->has($field)) aria-invalid="true" aria-describedby="{{ $modalId }}-{{ $field }}-error" @endif>
@if($failed) @error($field)<small class="field-error" id="{{ $modalId }}-{{ $field }}-error">{{ $message }}</small>@enderror @endif
</div>@endforeach
@if($page==='materi')<div class="lak-field"><label for="{{ $modalId }}-is_active">Digunakan dalam perhitungan</label><select class="lak-input" id="{{ $modalId }}-is_active" name="is_active"><option value="1" @selected(($failed ? old('is_active') : ($record?->is_active ?? 1))==1)>Aktif</option><option value="0" @selected(($failed ? old('is_active') : ($record?->is_active ?? 1))==0)>Nonaktif</option></select></div>@endif
</div>
</div>
<div class="crud-modal__foot"><div class="lak-form__actions"><button type="submit" class="lak-btn lak-btn--filled"><md-icon aria-hidden="true">save</md-icon>Simpan {{ $page }}</button><button type="button" class="lak-btn lak-btn--outlined" data-modal-close>Batal</button></div></div>
</form>
</dialog>
@endif
@endforeach
@endif
