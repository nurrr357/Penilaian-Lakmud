@foreach(['q','per_page','page'] as $key)<input type="hidden" name="_table[{{ $key }}]" value="{{ request()->query($key) }}">@endforeach
