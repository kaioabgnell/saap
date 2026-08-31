<section>
    <header>
        <h2 class="text-lg font-medium text-ink">Dados da clínica</h2>
        <p class="mt-1 text-sm text-ink-muted">
            Aparecem no formulário impresso e no relatório final. Tudo opcional.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        {{-- Os campos de dados pessoais viajam junto para que a validação de
             e-mail único não rejeite o envio deste formulário separado. --}}
        <input type="hidden" name="name" value="{{ old('name', $user->name) }}">
        <input type="hidden" name="email" value="{{ old('email', $user->email) }}">
        <input type="hidden" name="phone" value="{{ old('phone', $user->phone) }}">
        <input type="hidden" name="whatsapp" value="{{ old('whatsapp', $user->whatsapp) }}">
        <input type="hidden" name="council_id" value="{{ old('council_id', $user->council_id) }}">

        <div>
            <x-input-label for="clinic_name" value="Nome da clínica" />
            <x-text-input id="clinic_name" name="clinic_name" type="text" class="mt-1 block w-full" :value="old('clinic_name', $user->clinic_name)" />
            <x-input-error for="clinic_name" class="mt-2" :messages="$errors->get('clinic_name')" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <x-input-label for="clinic_phone" value="Telefone" />
                <x-text-input id="clinic_phone" name="clinic_phone" type="tel" class="mt-1 block w-full" :value="old('clinic_phone', $user->clinic_phone)" />
                <x-input-error for="clinic_phone" class="mt-2" :messages="$errors->get('clinic_phone')" />
            </div>

            <div>
                <x-input-label for="clinic_email" value="E-mail" />
                <x-text-input id="clinic_email" name="clinic_email" type="email" class="mt-1 block w-full" :value="old('clinic_email', $user->clinic_email)" />
                <x-input-error for="clinic_email" class="mt-2" :messages="$errors->get('clinic_email')" />
            </div>
        </div>

        <div>
            <x-input-label for="clinic_address" value="Endereço" />
            <x-text-input id="clinic_address" name="clinic_address" type="text" class="mt-1 block w-full" :value="old('clinic_address', $user->clinic_address)" />
            <x-input-error for="clinic_address" class="mt-2" :messages="$errors->get('clinic_address')" />
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <x-input-label for="clinic_city" value="Cidade" />
                <x-text-input id="clinic_city" name="clinic_city" type="text" class="mt-1 block w-full" :value="old('clinic_city', $user->clinic_city)" />
                <x-input-error for="clinic_city" class="mt-2" :messages="$errors->get('clinic_city')" />
            </div>

            <div>
                <x-input-label for="clinic_state" value="UF" />
                <x-text-input id="clinic_state" name="clinic_state" type="text" maxlength="2" class="mt-1 block w-full uppercase" :value="old('clinic_state', $user->clinic_state)" />
                <x-input-error for="clinic_state" class="mt-2" :messages="$errors->get('clinic_state')" />
            </div>
        </div>

        <div class="sm:w-1/3">
            <x-input-label for="clinic_zip" value="CEP" />
            <x-text-input id="clinic_zip" name="clinic_zip" type="text" class="mt-1 block w-full" :value="old('clinic_zip', $user->clinic_zip)" />
            <x-input-error for="clinic_zip" class="mt-2" :messages="$errors->get('clinic_zip')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>Salvar</x-primary-button>
        </div>
    </form>
</section>
