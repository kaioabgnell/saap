@php
    $aprendiz = $learner ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-medium text-ink">Nome</label>
        <input id="name" @error('name') aria-invalid="true" aria-describedby="name-erro" @enderror name="name" type="text" required autofocus
               value="{{ old('name', $aprendiz?->name) }}"
               class="mt-1 block w-full rounded-md border-line focus:border-primary focus:ring-primary">
        @error('name') <p id="name-erro" class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="birth_date" class="block text-sm font-medium text-ink">Data de nascimento</label>
        <input id="birth_date" @error('birth_date') aria-invalid="true" aria-describedby="birth_date-erro" @enderror name="birth_date" type="date" required
               value="{{ old('birth_date', $aprendiz?->birth_date?->toDateString()) }}"
               class="mt-1 block w-full rounded-md border-line focus:border-primary focus:ring-primary">
        @error('birth_date') <p id="birth_date-erro" class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="contact_phone" class="block text-sm font-medium text-ink">Telefone de contato</label>
        <input id="contact_phone" @error('contact_phone') aria-invalid="true" aria-describedby="contact_phone-erro" @enderror name="contact_phone" type="tel" placeholder="(00) 00000-0000"
               value="{{ old('contact_phone', $aprendiz?->contact_phone) }}"
               class="mt-1 block w-full rounded-md border-line focus:border-primary focus:ring-primary">
        @error('contact_phone') <p id="contact_phone-erro" class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="father_name" class="block text-sm font-medium text-ink">Nome do pai</label>
        <input id="father_name" @error('father_name') aria-invalid="true" aria-describedby="father_name-erro" @enderror name="father_name" type="text"
               value="{{ old('father_name', $aprendiz?->father_name) }}"
               class="mt-1 block w-full rounded-md border-line focus:border-primary focus:ring-primary">
        @error('father_name') <p id="father_name-erro" class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="mother_name" class="block text-sm font-medium text-ink">Nome da mãe</label>
        <input id="mother_name" @error('mother_name') aria-invalid="true" aria-describedby="mother_name-erro" @enderror name="mother_name" type="text"
               value="{{ old('mother_name', $aprendiz?->mother_name) }}"
               class="mt-1 block w-full rounded-md border-line focus:border-primary focus:ring-primary">
        @error('mother_name') <p id="mother_name-erro" class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="notes" class="block text-sm font-medium text-ink">Observações</label>
        <textarea id="notes" @error('notes') aria-invalid="true" aria-describedby="notes-erro" @enderror name="notes" rows="3"
                  class="mt-1 block w-full rounded-md border-line focus:border-primary focus:ring-primary">{{ old('notes', $aprendiz?->notes) }}</textarea>
        @error('notes') <p id="notes-erro" class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="photo" class="block text-sm font-medium text-ink">Foto</label>
        <div class="mt-1 flex items-center gap-4">
            @if ($aprendiz?->photoUrl('miniatura'))
                <img src="{{ $aprendiz->photoUrl('miniatura') }}" alt="" class="h-16 w-16 rounded-full object-cover">
            @endif
            <input id="photo" @error('photo') aria-invalid="true" aria-describedby="photo-erro" @enderror name="photo" type="file" accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-ink-muted file:mr-4 file:rounded-md file:border-0 file:bg-primary-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:bg-primary/20">
        </div>
        <p class="mt-1 text-xs text-ink-subtle">JPEG, PNG ou WebP, até 5 MB.</p>
        @error('photo') <p id="photo-erro" class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror

        {{-- Sem termo assinado não há base legal para guardar a imagem de uma
             criança. A caixa é exigida quando há foto, e desmarcá-la revoga a
             autorização e apaga a foto — está dito em voz alta abaixo, porque
             apagar dado sem avisar é pior que não apagar. --}}
        <div class="mt-4 rounded-md border border-line bg-canvas p-4">
            <label for="image_consent" class="flex min-h-[44px] items-start gap-3">
                <input id="image_consent" name="image_consent" type="checkbox" value="1"
                       @checked(old('image_consent', $aprendiz?->temConsentimentoDeImagem()))
                       @error('image_consent') aria-invalid="true" aria-describedby="image_consent-erro" @enderror
                       class="mt-0.5 h-5 w-5 flex-none rounded border-line text-primary focus:ring-primary">
                <span class="text-sm text-ink">
                    Os responsáveis assinaram o termo de autorização de uso de imagem.
                    <span class="mt-1 block text-xs text-ink-muted">
                        Obrigatório para enviar foto. O termo é assinado fora do sistema; aqui fica
                        registrada a data em que você atestou que ele existe.
                        @if ($aprendiz?->temConsentimentoDeImagem())
                            <span class="mt-1 block text-ink-subtle">
                                Registrado em {{ $aprendiz->image_consent_at->format('d/m/Y') }}.
                                Desmarcar revoga a autorização e <strong>apaga a foto</strong>.
                            </span>
                        @endif
                    </span>
                </span>
            </label>
            @error('image_consent')
                <p id="image_consent-erro" class="mt-2 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
