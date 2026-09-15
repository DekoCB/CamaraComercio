<li class="birthday-item">
    <div class="birthday-item-main">
        <a href="{{ route('associates.show', $event['associate']) }}" class="link-plain birthday-person">{{ $event['person'] }}</a>
        <span class="badge {{ $kindClass[$event['kind']] ?? 'badge-neutral' }}">{{ $event['kind_label'] }}</span>
    </div>
    <div class="birthday-item-meta">
        @if ($event['kind'] !== \App\Services\BirthdayService::KIND_ANNIVERSARY)
            <span>{{ $event['associate']->name }}</span>
        @endif
        <span>{{ $yearsLabel($event) }}</span>
        @if ($event['phone'])
            <span>{{ icon('phone', 'icon', 12) }} {{ $event['phone'] }}</span>
        @endif
        @if ($event['email'])
            <span>{{ icon('mail', 'icon', 12) }} {{ $event['email'] }}</span>
        @endif
    </div>
</li>
