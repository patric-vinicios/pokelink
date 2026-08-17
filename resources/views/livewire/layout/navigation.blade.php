<?php

use App\Livewire\Actions\Logout;
use App\Models\Favorite;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    /**
     * Pokémon detail routes keep Início highlighted (F04/F09).
     */
    public function inicioIsActive(): bool
    {
        return request()->routeIs('dashboard') || request()->routeIs('pokemon.*');
    }

    #[Computed]
    public function favoriteCount(): int
    {
        return Favorite::query()->where('user_id', auth()->id())->count();
    }

    #[On('favorite-toggled')]
    public function refreshFavoriteCount(): void
    {
        //
    }
}; ?>

<div x-data="{ open: false }">
    <svg class="absolute h-0 w-0" aria-hidden="true" focusable="false">
        <defs>
            <clipPath id="pokelink-topbar-clip" clipPathUnits="objectBoundingBox">
                <path d="M 0 0 H 1 V 1 H .74 C .67 1 .64 .48 .56 .48 H 0 Z" />
            </clipPath>
            <clipPath id="pokelink-sidebar-clip" clipPathUnits="objectBoundingBox">
                <path d="M 0 0 H 1 V .149 C 1 .245 .993 .324 .958 .412 C .91 .534 .743 .613 .521 .677 C .264 .749 .035 .77 .035 .807 C .042 .89 .347 .963 .819 1 H 0 Z" />
            </clipPath>
        </defs>
    </svg>

    <aside class="pokelink-sidebar">
        <div class="pokelink-sidebar-frame" aria-hidden="true">
            <img src="{{ asset('images/sidebar-frame.svg') }}" alt="" />
        </div>

        <a href="{{ route('dashboard') }}" wire:navigate class="pokelink-sidebar-logo" aria-label="Ir para o início">
            <x-application-logo class="h-auto w-full" />
        </a>

        <nav class="pokelink-sidebar-nav" aria-label="Navegação principal">
            <x-nav-link
                :href="route('dashboard')"
                :active="$this->inicioIsActive()"
                wire:navigate
                class="pokelink-sidebar-link"
                data-nav="home"
            >
                Início
            </x-nav-link>

            <x-nav-link
                :href="route('favoritos')"
                :active="request()->routeIs('favoritos')"
                wire:navigate
                class="pokelink-sidebar-link"
                data-nav="favorites"
            >
                Favoritos
                @if ($this->favoriteCount > 0)
                    <span class="pokelink-nav-badge">
                        {{ $this->favoriteCount > config('favorites.badge_cap') ? config('favorites.badge_cap').'+' : $this->favoriteCount }}
                    </span>
                @endif
            </x-nav-link>

            <x-nav-link
                :href="route('chat')"
                :active="request()->routeIs('chat')"
                wire:navigate
                class="pokelink-sidebar-link"
                data-nav="chat"
            >
                Chat
            </x-nav-link>

            <x-nav-link
                :href="route('perfil')"
                :active="request()->routeIs('perfil')"
                wire:navigate
                class="pokelink-sidebar-link"
                data-nav="profile"
            >
                Meu Perfil
            </x-nav-link>
        </nav>

        <div class="pokelink-sidebar-orbit" aria-hidden="true">
            <span></span>
        </div>

    </aside>

    <div class="pokelink-sidebar-pikachu" aria-hidden="true">
        <img src="{{ asset('images/pikachu-menu.png') }}" alt="" />
    </div>

    <header class="pokelink-topbar">
        <a href="{{ route('dashboard') }}" wire:navigate class="pokelink-mobile-brand w-32 lg:hidden" aria-label="PokéLink - início">
            <x-application-logo class="h-auto w-full" />
        </a>

        <div class="pokelink-desktop-profile hidden items-center gap-3 lg:flex">
            <a
                href="{{ route('dashboard') }}"
                wire:navigate
                class="pokelink-topball"
                aria-label="Voltar ao catálogo"
            >
                <span aria-hidden="true"></span>
            </a>

            <x-dropdown align="right" width="48">
                <x-slot name="trigger">
                    <button class="pokelink-profile-trigger" type="button">
                        <span class="min-w-0 text-left">
                            <span
                                class="block truncate text-sm font-extrabold text-white"
                                x-data="{{ json_encode(['name' => auth()->user()->name]) }}"
                                x-text="name"
                                x-on:profile-updated.window="name = $event.detail.name"
                            ></span>
                            <span class="block text-[10px] font-semibold text-blue-200">Treinador</span>
                        </span>
                        <svg class="h-4 w-4 text-blue-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <a href="{{ route('perfil') }}" wire:navigate class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        Meu Perfil
                    </a>
                    <button wire:click="logout" class="w-full text-start">
                        <x-dropdown-link>Sair</x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>

        <button
            type="button"
            class="pokelink-menu-button lg:hidden"
            @click="open = ! open"
            :aria-expanded="open"
            aria-controls="mobile-navigation"
            aria-label="Abrir menu"
        >
            <svg x-show="! open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 7h16M4 12h16M4 17h16" />
            </svg>
            <svg x-cloak x-show="open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </header>

    <div
        id="mobile-navigation"
        x-cloak
        x-show="open"
        x-transition.opacity
        class="pokelink-mobile-menu lg:hidden"
        @click.outside="open = false"
    >
        <div class="space-y-1 p-3">
            <x-responsive-nav-link :href="route('dashboard')" :active="$this->inicioIsActive()" wire:navigate class="pokelink-mobile-link" data-nav="home">
                Início
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('favoritos')" :active="request()->routeIs('favoritos')" wire:navigate class="pokelink-mobile-link" data-nav="favorites">
                Favoritos
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('chat')" :active="request()->routeIs('chat')" wire:navigate class="pokelink-mobile-link" data-nav="chat">
                Chat
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('perfil')" :active="request()->routeIs('perfil')" wire:navigate class="pokelink-mobile-link" data-nav="profile">
                Meu Perfil
            </x-responsive-nav-link>
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            <p class="font-extrabold text-slate-900">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
            <button wire:click="logout" class="mt-3 text-sm font-bold text-red-600">Sair</button>
        </div>
    </div>
</div>
