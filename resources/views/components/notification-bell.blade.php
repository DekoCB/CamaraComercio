@props(['notifications', 'unread'])

@php
    $categories = [
        'all' => 'Todas',
        \App\Models\Notification::TYPE_INVOICE_GENERATED.','.\App\Models\Notification::TYPE_INVOICE_VOIDED => 'Facturación',
        \App\Models\Notification::TYPE_PAYMENT_VOIDED => 'Pagos',
        \App\Models\Notification::TYPE_ASSOCIATE_BIRTHDAY => 'Cumpleaños',
    ];
@endphp

<div class="dropdown">
    <button class="icon-btn notification-bell" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificaciones">
        {{ icon('bell', 'icon', 18) }}
        @if ($unread > 0)
            <span class="notification-badge">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-end notification-panel">
        <div class="notification-panel-header">
            <strong>Notificaciones</strong>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="notification-mark-all">Marcar todo como leído</button>
                </form>
            @endif
        </div>

        <div class="notification-tabs js-notification-tabs">
            @foreach ($categories as $type => $label)
                <button type="button" class="notification-tab {{ $type === 'all' ? 'is-active' : '' }}" data-filter="{{ $type }}">
                    {{ $label }}
                    <span>{{ $type === 'all' ? $notifications->count() : $notifications->whereIn('type', explode(',', $type))->count() }}</span>
                </button>
            @endforeach
        </div>

        <div class="notification-list js-notification-list">
            @forelse ($notifications as $notification)
                <a href="{{ $notification->link ?? '#' }}" class="notification-item" data-type="{{ $notification->type }}">
                    <span class="notification-dot {{ $notification->isReadBy(auth()->id()) ? 'is-read' : '' }}"></span>
                    <span class="notification-body">
                        <strong>{{ $notification->title }}</strong>
                        @if ($notification->message)
                            <span class="notification-message">{{ $notification->message }}</span>
                        @endif
                        <span class="notification-time">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <div class="notification-empty">Sin notificaciones todavía.</div>
            @endforelse
        </div>
    </div>
</div>
