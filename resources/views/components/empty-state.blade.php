@props(['icon' => 'inbox', 'title', 'message' => null])
<div class="empty-state">
    <div class="state-icon">{{ icon($icon, 'icon', 26) }}</div>
    <h3>{{ $title }}</h3>
    @if ($message)
        <p>{{ $message }}</p>
    @endif
    {{ $slot ?? '' }}
</div>
