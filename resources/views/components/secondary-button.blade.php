<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-line bg-surface px-4 py-2 text-sm font-medium text-ink shadow-sm transition-colors hover:bg-canvas focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
