<?php

declare(strict_types=1);

namespace App\Domain\Contact;

/**
 * Telefone brasileiro normalizado para E.164.
 *
 * Existe por causa do lembrete: o link do WhatsApp exige o número em dígitos
 * puros com código do país, e `learners.contact_phone` é texto livre digitado
 * pela psicóloga — "(11) 90000-0000", "11 90000 0000", "+55 11 900000000".
 *
 * Quando não dá para normalizar com certeza, devolve **null**. Nunca chuta:
 * abrir o WhatsApp num número inventado é pior do que não abrir, porque a
 * conversa pode cair num desconhecido — e o assunto é a criança de alguém.
 */
final class PhoneNumber
{
    private function __construct(
        private readonly string $ddd,
        private readonly string $assinante,
    ) {}

    /**
     * Normaliza um telefone brasileiro. Devolve null quando o número não é
     * reconhecível — inclusive no caso ambíguo do celular antigo de 8 dígitos,
     * a que faltaria o nono dígito que não temos como adivinhar.
     */
    public static function doBrasil(?string $bruto): ?self
    {
        if ($bruto === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $bruto) ?? '';

        // Com código do país: 55 + DDD (2) + assinante (8 ou 9).
        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        if (strlen($digitos) !== 10 && strlen($digitos) !== 11) {
            return null;
        }

        $ddd = substr($digitos, 0, 2);
        $assinante = substr($digitos, 2);

        // Não existe DDD começado em 0, nem terminado em 0.
        if ($ddd[0] === '0' || $ddd[1] === '0') {
            return null;
        }

        // Celular: 9 dígitos, sempre iniciados por 9.
        if (strlen($assinante) === 9 && $assinante[0] !== '9') {
            return null;
        }

        // Fixo: 8 dígitos, iniciados por 2 a 5. Um número de 8 dígitos
        // começando em 6–9 é celular no formato antigo: recusamos em vez de
        // inserir o nono dígito por conta própria.
        if (strlen($assinante) === 8 && ! in_array($assinante[0], ['2', '3', '4', '5'], true)) {
            return null;
        }

        return new self($ddd, $assinante);
    }

    /** "+5511900000000" */
    public function paraE164(): string
    {
        return '+55'.$this->ddd.$this->assinante;
    }

    /** "5511900000000" — é o formato que o wa.me espera, sem o "+". */
    public function paraWhatsApp(): string
    {
        return '55'.$this->ddd.$this->assinante;
    }

    /** "(11) 90000-0000" — só para exibição. */
    public function formatado(): string
    {
        $corte = strlen($this->assinante) === 9 ? 5 : 4;

        return sprintf(
            '(%s) %s-%s',
            $this->ddd,
            substr($this->assinante, 0, $corte),
            substr($this->assinante, $corte),
        );
    }

    public function isCelular(): bool
    {
        return strlen($this->assinante) === 9;
    }
}
