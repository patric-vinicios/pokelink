<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Meu Perfil
        </h2>
    </x-slot>

    <div class="space-y-6">
        <x-card>
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </x-card>

        <x-card>
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </x-card>
    </div>
</x-app-layout>
