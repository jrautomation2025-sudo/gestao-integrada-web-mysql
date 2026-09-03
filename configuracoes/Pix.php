<?php
class Pix {
    private $chave_pix;
    private $beneficiario;
    private $cidade;
    private $valor;
    private $identificador;

    public function __construct($chave_pix, $beneficiario, $cidade, $valor, $identificador = "***") {
        $this->chave_pix = $chave_pix;
        $this->beneficiario = substr($beneficiario, 0, 25);
        $this->cidade = substr($cidade, 0, 15);
        $this->valor = number_format($valor, 2, '.', '');
        $this->identificador = $identificador;
    }

    private function getTamanho($valor) {
        return str_pad(strlen($valor), 2, '0', STR_PAD_LEFT);
    }

    private function getValue($id, $valor) {
        return $id . $this->getTamanho($valor) . $valor;
    }

    public function getPayload() {
        $payload = $this->getValue('00', '01');
        $gui = $this->getValue('00', 'br.gov.bcb.pix');
        $chave = $this->getValue('01', $this->chave_pix);
        $infoConta = $this->getValue('26', $gui . $chave);
        $mcc = $this->getValue('52', '0000');
        $moeda = $this->getValue('53', '986');
        $valor = $this->getValue('54', $this->valor);
        $pais = $this->getValue('58', 'BR');
        $nome = $this->getValue('59', $this->beneficiario);
        $cidade = $this->getValue('60', $this->cidade);
        $txid = $this->getValue('05', $this->identificador);
        $dadosAdicionais = $this->getValue('62', $txid);
        
        $payload .= $infoConta . $mcc . $moeda . $valor . $pais . $nome . $cidade . $dadosAdicionais;
        $payload .= '6304';
        $payload .= $this->calculaCRC16($payload);
        return $payload;
    }

    private function calculaCRC16($payload) {
        $polinomio = 0x1021;
        $resultado = 0xFFFF;
        if (($length = strlen($payload)) > 0) {
            for ($offset = 0; $offset < $length; $offset++) {
                $resultado ^= (ord($payload[$offset]) << 8);
                for ($bitwise = 0; $bitwise < 8; $bitwise++) {
                    if (($resultado <<= 1) & 0x10000) $resultado ^= $polinomio;
                    $resultado &= 0xFFFF;
                }
            }
        }
        return strtoupper(str_pad(dechex($resultado), 4, '0', STR_PAD_LEFT));
    }
}