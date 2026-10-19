@php
    [$field, $label] = match($page) {
        'absensi' => ['attendance', 'Absensi'],
        'pretest' => ['pretest', 'Pretest'],
        'posttest' => ['posttest', 'Posttest'],
        'keaktifan' => ['activity', 'Keaktifan'],
        'etika' => ['ethics', 'Etika'],
        'pemahaman' => ['understanding', 'Pemahaman'],
    };
    $canInput = auth()->user()->can(\App\Services\PanelPermissions::INPUTS[$page]);
@endphp
<p>{{ $field === 'attendance' ? 'Catat status hadir atau tidak hadir untuk satu peserta dan materi.' : 'Nilai 10 sampai 20, maksimal dua desimal.' }} Data disimpan per peserta dan materi.</p>
@if($material && $participants->isNotEmpty())
<form method="get" class="filter"><input type="hidden" name="event_id" value="{{ $event->id }}"><input type="hidden" name="q" value="{{ $q }}"><input type="hidden" name="per_page" value="{{ $listing->perPage() }}"><label>Materi<select name="material_id">@foreach($active as $option)<option value="{{ $option->id }}" @selected($material->id===$option->id)>M{{ $option->sequence }}: {{ $option->name }}</option>@endforeach</select></label><button class="secondary">Pilih materi</button></form>
<section class="card"><h2>{{ $event->name }}: {{ $material->name }}</h2>
<div class="table-wrap" tabindex="0" aria-label="Tabel {{ strtolower($label) }}"><table><thead><tr><th>No.</th><th>Peserta</th><th>{{ $label }}</th><th>Aksi</th></tr></thead><tbody>
@forelse($listing as $participant)
@php
    $value = $inputs->get($participant->id.':'.$material->id)?->$field;
    $display = $field === 'attendance' ? ($value === 'present' ? 'Hadir' : ($value === 'absent' ? 'Tidak hadir' : 'Belum diisi')) : ($value ?? 'Belum diisi');
    $modalId = 'score-'.$participant->id;
@endphp
<tr><td>{{ $listing->firstItem()+$loop->index }}</td><th scope="row">{{ $participant->name }}</th><td>{{ $display }}</td><td>@if($canInput && !$event->finalized)<button type="button" class="secondary" data-modal-open="{{ $modalId }}">{{ $value === null ? 'Tambah' : 'Ubah' }}</button>@else<span>Hanya baca</span>@endif</td></tr>
@empty<tr><td colspan="4">{{ $q !== '' ? 'Tidak ada peserta sesuai pencarian.' : 'Belum ada peserta untuk kegiatan ini.' }}</td></tr>@endforelse
</tbody></table></div></section>
@if($canInput && !$event->finalized)
@foreach($listing as $participant)
@php
    $modalId = 'score-'.$participant->id;
    $failed = $errors->any() && old('_modal') === $modalId;
    $value = $failed ? old('rows.'.$participant->id.'.'.$field) : $inputs->get($participant->id.':'.$material->id)?->$field;
@endphp
<dialog id="{{ $modalId }}" class="crud-modal" aria-labelledby="{{ $modalId }}-title" @if($failed) data-reopen @endif>
<h2 id="{{ $modalId }}-title">{{ $value === null ? 'Tambah' : 'Ubah' }} {{ strtolower($label) }}</h2><p><strong>{{ $participant->name }}</strong><br>{{ $material->name }}</p>
@if($failed)<div class="error" role="alert"><strong>Data belum disimpan.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="/input/{{ $event->id }}/{{ $page }}">@csrf @include('table-state')<input type="hidden" name="material_id" value="{{ $material->id }}"><input type="hidden" name="_modal" value="{{ $modalId }}">
@if($field === 'attendance')<label for="{{ $modalId }}-value">Status absensi<select id="{{ $modalId }}-value" name="rows[{{ $participant->id }}][attendance]" required @if($failed && $errors->has('rows.'.$participant->id.'.attendance')) aria-invalid="true" aria-describedby="{{ $modalId }}-value-error" @endif><option value="">Pilih status</option><option value="present" @selected($value==='present')>Hadir</option><option value="absent" @selected($value==='absent')>Tidak hadir</option></select>@if($failed) @error('rows.'.$participant->id.'.attendance')<small class="field-error" id="{{ $modalId }}-value-error">{{ $message }}</small>@enderror @endif</label>
@else<label for="{{ $modalId }}-value">Nilai {{ strtolower($label) }}<input id="{{ $modalId }}-value" name="rows[{{ $participant->id }}][{{ $field }}]" type="number" min="10" max="20" step="0.01" value="{{ $value }}" required @if($failed && $errors->has('rows.'.$participant->id.'.'.$field)) aria-invalid="true" aria-describedby="{{ $modalId }}-value-error" @endif>@if($failed) @error('rows.'.$participant->id.'.'.$field)<small class="field-error" id="{{ $modalId }}-value-error">{{ $message }}</small>@enderror @endif</label>@endif
<div class="actions"><button>Simpan {{ strtolower($label) }}</button><button type="button" class="secondary" data-modal-close>Batal</button></div></form></dialog>
@endforeach
@endif
@else<p class="card">Tambahkan materi aktif dan peserta melalui Data kegiatan.</p>@endif