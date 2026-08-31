<section>
    <header>
        <h2 class="text-lg font-medium text-ink">Dados pessoais</h2>
        <p class="mt-1 text-sm text-ink-muted">Atualize seus dados, foto e o endereço de e-mail.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div class="flex items-center gap-4">
            @if ($user->photoUrl('miniatura'))
                <img src="{{ $user->photoUrl('miniatura') }}" alt="" class="h-16 w-16 rounded-full object-cover">
            @else
                <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-soft">
                    <i class="fa-solid fa-user text-2xl text-primary" aria-hidden="true"></i>
                </span>
            @endif
            <div class="flex-1">
                <x-input-label for="photo" value="Foto" />
                <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                       class="mt-1 block w-full text-sm text-ink-muted file:mr-4 file:rounded-md file:border-0 file:bg-primary-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20">
            </div>
        </div>
        <x-input-error for="photo" class="mt-2" :messages="$errors->get('photo')" />

        <div>
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error for="name" class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error for="email" class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-ink-muted">
                        Seu e-mail ainda não foi confirmado.

                        <button form="send-verification" class="underline text-sm text-ink-muted hover:text-ink rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            Clique aqui para reenviar o e-mail de confirmação.
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-success-ink">
                            Um novo link de confirmação foi enviado para o seu e-mail.
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="phone" value="Telefone" />
                <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" />
                <x-input-error for="phone" class="mt-2" :messages="$errors->get('phone')" />
            </div>

            <div>
                <x-input-label for="whatsapp" value="WhatsApp" />
                <x-text-input id="whatsapp" name="whatsapp" type="tel" class="mt-1 block w-full" :value="old('whatsapp', $user->whatsapp)" />
                <x-input-error for="whatsapp" class="mt-2" :messages="$errors->get('whatsapp')" />
            </div>

            <div>
                <x-input-label for="council_id" value="Registro profissional (CRP)" />
                <x-text-input id="council_id" name="council_id" type="text" class="mt-1 block w-full" :value="old('council_id', $user->council_id)" />
                <x-input-error for="council_id" class="mt-2" :messages="$errors->get('council_id')" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Salvar</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-ink-muted"
                >Salvo.</p>
            @endif
        </div>
    </form>
</section>
