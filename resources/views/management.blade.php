@php
    $permission = match($page) {'kegiatan'=>'events.manage','materi'=>'materials.manage',default=>'participants.manage'};
    $canManage = auth()->user()->can($permission);
    $canAdd = $page === 'kegiatan' || ($event && !$event->finalized);
@endphp
<section class="card">
@if($page !== 'kegiatan')
    <h2>{{ $event?->name ?? 'Belum ada kegiatan' }}</h2>
    <p>{{ $page === 'materi' ? 'Urutan 1 sampai 15 harus unik. Hanya materi aktif dihitung.' : 'Nomor urut peserta harus unik di kegiatan ini.' }}</p>
@endif
@if($canManage && $canAdd)<button type="button" data-modal-open="create">Tambah {{ $page }}</button>@endif
<div class="table-wrap" tabindex="0" aria-label="Tabel {{ $page }}">
<table><thead><tr><th>No.</th><th>Nama {{ $page }}</th>@if($page==='kegiatan')<th>Organisasi / tempat</th><th>Tanggal</th><th>Status</th>@else<th>Nomor urut</th>@if($page==='materi')<th>Status</th>@endif @endif<th>Aksi</th></tr></thead><tbody>
@forelse($listing as $record)
@php
    $locked = $page==='kegiatan' ? $record->finalized : $event->finalized;
@endphp
<tr><td>{{ $listing->firstItem()+$loop->index }}</td><th scope="row">{{ $record->name }}</th>
@if($page==='kegiatan')<td>{{ $record->organization }}<br>{{ $record->venue }}</td><td>{{ $record->start_date }} sampai {{ $record->end_date }}</td><td>{{ $locked ? 'Dikunci' : 'Terbuka' }}</td>
@else<td>{{ $record->sequence }}</td>@if($page==='materi')<td>{{ $record->is_active ? 'Aktif' : 'Nonaktif' }}</td>@endif @endif
<td><div class="actions">
@if($canManage)<button type="button" class="secondary" @if(!$locked) data-modal-open="edit-{{ $record->id }}" @endif @disabled($locked)>Ubah</button>
<form method="post" action="{{ $page==='kegiatan' ? '/kegiatan/'.$record->id : '/kegiatan/'.$event->id.'/'.$page.'/'.$record->id }}" data-confirm="Hapus {{ $record->name }} beserta input terkait?">@csrf @method('DELETE')<button class="danger" @disabled($locked)>Hapus</button></form>@else<span>Hanya baca</span>@endif
</div></td></tr>
@empty<tr><td colspan="6">{{ $q !== '' ? 'Tidak ada data sesuai pencarian. Ubah kata pencarian.' : 'Belum ada data. Tambahkan data jika Anda memiliki izin.' }}</td></tr>@endforelse
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
<h2 id="{{ $modalId }}-title">{{ $record ? 'Ubah' : 'Tambah' }} {{ $page }}</h2>
@if($page!=='kegiatan')<p>Kegiatan: <strong>{{ $event->name }}</strong></p>@endif
@if($failed)<div class="error" role="alert"><strong>Data belum disimpan.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ $page==='kegiatan' ? '/kegiatan'.($record ? '/'.$record->id : '') : '/kegiatan/'.$event->id.'/'.$page.($record ? '/'.$record->id : '') }}">
@csrf @include('table-state') @if($record) @method('PUT') @endif
<input type="hidden" name="_modal" value="{{ $modalId }}">
@php($fields = $page==='kegiatan' ? ['name'=>'Nama kegiatan','organization'=>'Organisasi','venue'=>'Tempat','start_date'=>'Tanggal mulai','end_date'=>'Tanggal selesai'] : ['name'=>'Nama '.$page,'sequence'=>'Nomor urut'])
<div class="form-grid">@foreach($fields as $field=>$label)
<label for="{{ $modalId }}-{{ $field }}">{{ $label }}
<input id="{{ $modalId }}-{{ $field }}" name="{{ $field }}" type="{{ str_contains($field,'date') ? 'date' : ($field==='sequence' ? 'number' : 'text') }}" value="{{ $failed ? old($field) : $record?->$field }}" @if($field==='sequence') min="1" max="{{ $page==='materi' ? 15 : 99999 }}" @else maxlength="200" @endif required @if($failed && $errors->has($field)) aria-invalid="true" aria-describedby="{{ $modalId }}-{{ $field }}-error" @endif>
@if($failed) @error($field)<small class="field-error" id="{{ $modalId }}-{{ $field }}-error">{{ $message }}</small>@enderror @endif
</label>@endforeach
@if($page==='materi')<label>Digunakan dalam perhitungan<select name="is_active"><option value="1" @selected(($failed ? old('is_active') : ($record?->is_active ?? 1))==1)>Aktif</option><option value="0" @selected(($failed ? old('is_active') : ($record?->is_active ?? 1))==0)>Nonaktif</option></select></label>@endif
</div><div class="actions"><button>Simpan {{ $page }}</button><button type="button" class="secondary" data-modal-close>Batal</button></div>
</form></dialog>
@endif
@endforeach
@endif
