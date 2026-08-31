<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Learner;
use Illuminate\Foundation\Http\FormRequest;

class StoreLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Learner::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'birth_date' => [
                'required', 'date',
                'before_or_equal:today',
                'after_or_equal:'.now()->subYears(30)->toDateString(),
            ],
            'father_name' => ['nullable', 'string', 'max:160'],
            'mother_name' => ['nullable', 'string', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // A caixa não é formalidade: sem termo assinado pelos responsáveis
            // não há base legal para guardar a imagem de uma criança. Por isso
            // ela é EXIGIDA quando há foto, e não apenas registrada.
            // A regra depende de haver foto, e precisa ser escrita assim:
            // `accepted` é regra IMPLÍCITA no Laravel, então roda mesmo com o
            // campo ausente e `nullable` não a pula — um cadastro sem foto
            // seria recusado por falta de uma autorização que não faz falta.
            'image_consent' => $this->hasFile('photo')
                ? ['required', 'accepted']
                : ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image_consent.required' => 'Para enviar a foto, confirme que os responsáveis assinaram o termo de uso de imagem.',
            'image_consent.accepted' => 'Para enviar a foto, confirme que os responsáveis assinaram o termo de uso de imagem.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'birth_date' => 'data de nascimento',
            'father_name' => 'nome do pai',
            'mother_name' => 'nome da mãe',
            'contact_phone' => 'telefone de contato',
            'notes' => 'observações',
            'photo' => 'foto',
            'image_consent' => 'autorização de uso de imagem',
        ];
    }
}
