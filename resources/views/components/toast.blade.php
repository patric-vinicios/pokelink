{{--
    Any component can notify the user by dispatching a browser event:

        $this->dispatch('toast', message: 'Adicionado aos favoritos.', type: 'success');

    `type` is optional and defaults to 'success'; pass 'error' for the red variant.
--}}
<div
    x-data="{
        toasts: [],
        add(message, type) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, type: type ?? 'success' });
            if (this.toasts.length > 3) {
                this.toasts.shift();
            }
            setTimeout(() => this.remove(id), 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }"
    x-on:toast.window="add($event.detail.message, $event.detail.type)"
    class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'"
            class="pointer-events-auto text-white text-sm rounded-md shadow-lg px-4 py-3 max-w-sm"
            x-text="toast.message"
        ></div>
    </template>
</div>
