@extends('layout')
@section('content')
<div class="page-header">
    <div>
        <h1>Log Aktivitas</h1>
        <p class="subtitle">Riwayat tindakan pengguna untuk audit dan penelusuran perubahan.</p>
    </div>
</div>

<section class="lak-card">
    <div class="lak-table-wrap">
        <table class="lak-table">
            <thead>
                <tr>
                    <th scope="col">Waktu</th>
                    <th scope="col">Pengguna</th>
                    <th scope="col">Aksi</th>
                    <th scope="col">Subjek</th>
                    <th scope="col">Detail</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $properties = is_array($log->properties)
                            ? $log->properties
                            : (json_decode((string) $log->properties, true) ?: []);
                        $sanitize = function ($value) use (&$sanitize) {
                            if (! is_array($value)) {
                                return $value;
                            }

                            return collect($value)
                                ->reject(fn ($item, $key) => in_array(strtolower((string) $key), ['password', 'password_confirmation', 'current_password', '_token', 'remember_token'], true))
                                ->map(fn ($item) => $sanitize($item))
                                ->all();
                        };
                        $details = $sanitize($properties);
                        $subject = $log->subject_type
                            ? class_basename($log->subject_type).($log->subject_id ? ' #'.$log->subject_id : '')
                            : '—';
                    @endphp
                    <tr>
                        <td>{{ $log->created_at ? \Illuminate\Support\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') : '—' }}</td>
                        <td>
                            <strong>{{ $log->causer_name ?? 'Pengguna tidak tersedia' }}</strong>
                            @if($log->causer_email)<div class="muted">{{ $log->causer_email }}</div>@endif
                        </td>
                        <td>
                            <strong>{{ $log->description }}</strong>
                            @if($log->log_name)<div class="muted">{{ $log->log_name }}</div>@endif
                        </td>
                        <td>{{ $subject }}</td>
                        <td>
                            @if($details)
                                <details>
                                    <summary>Lihat detail</summary>
                                    <pre class="lak-log-json">{{ json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @else
                                <span class="muted">Tidak ada detail</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5">Belum ada aktivitas yang tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="lak-form__actions">
        <span class="muted">Menampilkan {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} dari {{ $logs->total() }} aktivitas</span>
        {{ $logs->links() }}
    </div>
</section>
@endsection
