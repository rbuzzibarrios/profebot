<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../profebot.php';

/**
 * Tests for pb_valid_question(), the cache-poison guard that validates
 * AI-generated questions before they are stored or served.
 */
final class ValidQuestionTest extends TestCase
{
    /** Build a question array with sensible defaults that can be overridden. */
    private function makeQ(array $overrides = []): array
    {
        $base = [
            'question'    => '¿Cuánto es 2 + 2?',
            'opts'        => ['A' => 'Tres', 'B' => 'Cuatro', 'C' => 'Cinco', 'D' => 'Seis'],
            'correct'     => 'B',
            'explanation' => 'Dos más dos son cuatro.',
        ];

        return array_replace($base, $overrides);
    }

    public function testValidFourOptionQuestionForFirstGrade(): void
    {
        $q = $this->makeQ();
        $this->assertTrue(pb_valid_question($q, '1ro::mat_u1::easy'));
    }

    public function testValidTwoOptionQuestionForPreescolar(): void
    {
        $q = $this->makeQ([
            'opts'    => ['A' => 'Rojo', 'B' => 'Azul'],
            'correct' => 'A',
        ]);
        $this->assertTrue(pb_valid_question($q, 'preesc::mat_conjuntos::easy'));
    }

    public function testNonArrayInputIsRejected(): void
    {
        $this->assertFalse(pb_valid_question('not an array'));
        $this->assertFalse(pb_valid_question(null));
        $this->assertFalse(pb_valid_question(42));
    }

    public function testEmptyQuestionIsRejected(): void
    {
        $this->assertFalse(pb_valid_question($this->makeQ(['question' => ''])));
        $this->assertFalse(pb_valid_question($this->makeQ(['question' => '   '])));
    }

    public function testMissingQuestionIsRejected(): void
    {
        $q = $this->makeQ();
        unset($q['question']);
        $this->assertFalse(pb_valid_question($q));
    }

    public function testQuestionOverTwoHundredCharsIsRejected(): void
    {
        $longQuestion = str_repeat('a', 201);
        $this->assertFalse(pb_valid_question($this->makeQ(['question' => $longQuestion])));
    }

    public function testQuestionAtTwoHundredCharsIsAccepted(): void
    {
        $maxQuestion = str_repeat('a', 200);
        $this->assertTrue(pb_valid_question($this->makeQ(['question' => $maxQuestion]), '1ro::mat::easy'));
    }

    #[DataProvider('missingRequiredOptionProvider')]
    public function testMissingOptionAOrBIsRejected(array $opts, string $correct): void
    {
        $q = $this->makeQ(['opts' => $opts, 'correct' => $correct]);
        $this->assertFalse(pb_valid_question($q, '1ro::mat::easy'));
    }

    public static function missingRequiredOptionProvider(): array
    {
        return [
            'missing A'      => [['B' => 'Cuatro', 'C' => 'Cinco'], 'B'],
            'missing B'      => [['A' => 'Tres', 'C' => 'Cinco'], 'A'],
            'A empty'        => [['A' => '', 'B' => 'Cuatro'], 'B'],
            'B whitespace'   => [['A' => 'Tres', 'B' => '   '], 'A'],
            'no opts at all' => [[], 'A'],
        ];
    }

    public function testCorrectLetterNotAmongPresentOptionsIsRejected(): void
    {
        // correct "C" but only A and B are present
        $q = $this->makeQ([
            'opts'    => ['A' => 'Tres', 'B' => 'Cuatro'],
            'correct' => 'C',
        ]);
        $this->assertFalse(pb_valid_question($q, '1ro::mat::easy'));
    }

    public function testCorrectLetterPointingAtEmptyOptionIsRejected(): void
    {
        // C exists as a key but is empty, so correct "C" must fail
        $q = $this->makeQ([
            'opts'    => ['A' => 'Tres', 'B' => 'Cuatro', 'C' => ''],
            'correct' => 'C',
        ]);
        $this->assertFalse(pb_valid_question($q, '1ro::mat::easy'));
    }

    public function testCorrectLetterIsCaseInsensitive(): void
    {
        $q = $this->makeQ(['correct' => 'b']);
        $this->assertTrue(pb_valid_question($q, '1ro::mat::easy'));
    }

    #[DataProvider('leakedMarkerProvider')]
    public function testLeakedFormatMarkersAreRejected(string $field, string $value): void
    {
        $q = $this->makeQ([$field => $value]);
        $this->assertFalse(pb_valid_question($q, '1ro::mat::easy'));
    }

    public static function leakedMarkerProvider(): array
    {
        return [
            'PREGUNTA in question'         => ['question', 'PREGUNTA: ¿Cuánto es 2 + 2?'],
            'CORRECTA in explanation'      => ['explanation', 'CORRECTA: B'],
            'EXPLICACION in explanation'   => ['explanation', 'EXPLICACION: porque sí'],
            'EXPLICACIÓN accent in expl.'  => ['explanation', 'EXPLICACIÓN: porque sí'],
            'PREGUNTA in explanation'      => ['explanation', 'Siguiente PREGUNTA: otra'],
        ];
    }

    public function testPreescolarWithOptionCIsRejected(): void
    {
        $q = $this->makeQ([
            'opts'    => ['A' => 'Rojo', 'B' => 'Azul', 'C' => 'Verde'],
            'correct' => 'A',
        ]);
        $this->assertFalse(pb_valid_question($q, 'preesc::mat_conjuntos::easy'));
    }

    public function testPreescolarWithOptionDIsRejected(): void
    {
        $q = $this->makeQ([
            'opts'    => ['A' => 'Rojo', 'B' => 'Azul', 'D' => 'Amarillo'],
            'correct' => 'A',
        ]);
        $this->assertFalse(pb_valid_question($q, 'preesc::mat_conjuntos::easy'));
    }

    public function testFourOptionQuestionWithEmptyKeyIsAccepted(): void
    {
        // No "preesc" prefix means the 2-option rule does not apply.
        $this->assertTrue(pb_valid_question($this->makeQ(), ''));
    }
}
