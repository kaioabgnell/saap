<section>
    <header>
        <h2 class="text-lg font-medium text-ink">Dados da clínica</h2>
        <p class="mt-1 text-sm text-ink-muted">
            Aparecem no formulário impresso e no relatório final. Tudo opcional.
        </p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
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

        {{-- Logo da clínica: é o que substitui o "SAAP" no cabeçalho do
             relatório em PDF. A pré-visualização mostra exatamente o que sai
             no laudo — inclusive a logo do sistema, quando não há nenhuma
             própria (ver BuildReportPayload::logoEmBase64()). --}}
        <div>
            <x-input-label value="Logo da clínica" />
            <p class="mt-1 text-xs text-ink-subtle">
                Aparece no cabeçalho do relatório em PDF. Sem logo própria, o
                relatório usa a logo do SAAP.
            </p>

            <div class="mt-3 flex items-center gap-4">
                <div class="flex h-16 w-32 flex-none items-center justify-center rounded-md border border-line bg-canvas p-2">
                    @if ($user->clinicLogoUrl())
                        <img src="{{ $user->clinicLogoUrl() }}" alt="Logo da clínica" class="max-h-full max-w-full object-contain">
                    @else
                        <x-application-logo class="max-h-8 max-w-full" />
                    @endif
                </div>

                <div class="flex-1">
                    <input id="clinic_logo" name="clinic_logo" type="file" accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-ink-muted file:mr-4 file:rounded-md file:border-0 file:bg-primary-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20">
                    <p class="mt-1 text-xs text-ink-subtle">JPEG, PNG ou WebP, até 2&nbsp;MB.</p>

                    @if ($user->clinicLogoUrl())
                        <label for="remove_clinic_logo" class="mt-2 flex min-h-[28px] items-center gap-2">
                            <input id="remove_clinic_logo" name="remove_clinic_logo" type="checkbox" value="1"
                                   class="h-4 w-4 rounded border-line text-primary focus:ring-primary">
                            <span class="text-sm text-ink-muted">Remover logo e voltar a usar a do SAAP</span>
                        </label>
                    @endif
                </div>
            </div>
            <x-input-error for="clinic_logo" class="mt-2" :messages="$errors->get('clinic_logo')" />
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
