<?php declare(strict_types=1);

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program. If not, see <https://www.gnu.org/licenses/agpl-3.0.txt>.
 */

namespace Bitnix\Dotenv;

use Bitnix\Parse\Lexer,
    Bitnix\Parse\ParseFailure,
    Bitnix\Parse\Lexer\Scanner,
    Bitnix\Parse\Lexer\State,
    Bitnix\Parse\Lexer\TokenSet,
    Bitnix\Parse\Lexer\TokenStream;

/**
 * @version 0.1.0
 */
final class Parser {

    private const T_WHITESPACE   = 'T_WHITESPACE';
    private const T_COMMENT      = 'T_COMMENT';
    private const T_EXPORT       = 'T_EXPORT';
    private const T_VAR_NAME     = 'T_VAR_NAME';
    private const T_VAR_VALUE    = 'T_VAR_VALUE';
    private const T_ASSIGN       = 'T_ASSIGN';
    private const T_SINGLE_QUOTE = 'T_SINGLE_QUOTE';
    private const T_RAW_TEXT     = 'T_RAW_TEXT';
    private const T_DOUBLE_QUOTE = 'T_DOUBLE_QUOTE';
    private const T_EOL          = 'T_EOL';
    private const T_EOS          = 'T_EOS';

    private const MAIN_STATE = [
        self::T_WHITESPACE => '\s+',
        self::T_COMMENT    => '#[^\r\n]*',
        self::T_EXPORT     => '\bexport\b',
        self::T_VAR_NAME   => '(?i)[a-z][a-z0-9_\.]*'
    ];

    private const VALUE_STATE = [
        self::T_COMMENT      => '[ \t]*#[^\r\n]*',
        self::T_ASSIGN       => '=',
        self::T_SINGLE_QUOTE => "'",
        self::T_DOUBLE_QUOTE => '"',
        self::T_VAR_VALUE    => '[^\s]+',
        self::T_EOL          => '\r?\n'
    ];

    private const SQ_STRING_STATE = [
        self::T_RAW_TEXT     => "([^'\\\\]|\\\\(')?)+",
        self::T_SINGLE_QUOTE => "'"
    ];

    private const DQ_STRING_STATE = [
        self::T_RAW_TEXT     => '([^"\\\\]|\\\\(")?)+',
        self::T_DOUBLE_QUOTE => '"'
    ];

    private const BOOL_VALUES = [
        'true'  => true,
        'on'    => true,
        'yes'   => true,
        'false' => false,
        'off'   => false,
        'no'    => false
    ];

    /**
     * @var Lexer
     */
    private ?Lexer $lexer = null;

    /**
     * @var State
     */
    private State $main;

    /**
     * ...
     *
     */
    public function __construct() {
        $this->main = new TokenSet(self::MAIN_STATE);

        $value = new TokenSet(self::VALUE_STATE);
        $sqstr = new TokenSet(self::SQ_STRING_STATE);
        $dqstr = new TokenSet(self::DQ_STRING_STATE);

        $skip = fn($state) => $state->skip();
        $pop = fn($state) => $state->pop();

        $this->main
            ->on(self::T_COMMENT, $skip)
            ->on(self::T_WHITESPACE, $skip)
            ->on(self::T_EXPORT, $skip)
            ->on(self::T_VAR_NAME, fn($state) => $state->push($value));

        $value
            ->on(self::T_COMMENT, $skip)
            ->on(self::T_EOL, $pop)
            ->on(self::T_SINGLE_QUOTE, fn($state) => $state->push($sqstr))
            ->on(self::T_DOUBLE_QUOTE, fn($state) => $state->push($dqstr));

        $sqstr->on(self::T_SINGLE_QUOTE, $pop);
        $dqstr->on(self::T_DOUBLE_QUOTE, $pop);
    }

    /**
     * @param string $content
     * @return array
     * @throws ParseFailure
     * @throws RuntimeException
     */
    public function parse(string $content) : array {
        $env = [];
        try {
            $this->lexer = new Scanner(new TokenStream($this->main, $content));
            while (!$this->lexer->match(self::T_EOS)) {

                $name = $this->lexer->demand(self::T_VAR_NAME)->lexeme();
                $this->lexer->demand(self::T_ASSIGN);

                $value = null;

                if ($token = $this->lexer->consume(self::T_VAR_VALUE)) {
                    $value = $this->value($token->lexeme(), true, true, $env);
                } else if ($this->lexer->consume(self::T_SINGLE_QUOTE)) {
                    $value = '';
                    while ($token = $this->lexer->consume(self::T_RAW_TEXT)) {
                        $value .= $token->lexeme();
                    }
                    $this->lexer->demand(self::T_SINGLE_QUOTE);
                    $value = \str_replace("\'", "'", $this->value($value, false, false));
                } else if ($this->lexer->consume(self::T_DOUBLE_QUOTE)) {
                    $value = '';
                    while ($token = $this->lexer->consume(self::T_RAW_TEXT)) {
                        $value .= $token->lexeme();
                    }
                    $this->lexer->demand(self::T_DOUBLE_QUOTE);
                    $value = \str_replace(
                        ['\"', '\r', '\n', '\t'],
                        ['"', "\r", "\n", "\t"],
                        $this->value($value, false, true, $env)
                    );
                }

                if (!$this->lexer->match(self::T_EOS)) {
                    $this->lexer->demand(self::T_EOL);
                }

                $env[$name] = $value;
            }
        } finally {
            $this->lexer = null;
        }

        return $env;
    }

    /**
     * @param string $value
     * @param bool $cast
     * @param bool $unfold
     * @param null|array $env
     * @return mixed
     */
    private function value(string $value, bool $cast, bool $unfold, array $env = null) {

        if (preg_match('~\s~', $value)) {
            $value = \preg_replace('~[\s]+~', ' ', $value);
            if ($unfold) {
                $value = \preg_replace('~\\\\ ~', '', $value);
            }
        } else if ($cast) {
            $test = \strtolower($value);

            if ('null' === $test) {
                return null;
            } else if (isset(self::BOOL_VALUES[$test])) {
                return self::BOOL_VALUES[$test];
            } else if (\is_numeric($test)) {
                $number = (int) $test;
                if ($number == $test) {
                    return $number;
                }

                $number = (float) $test;
                if ($number == $test) {
                    return $number;
                }
            } else if (\defined($value)) {
                return \constant($value);
            }
        }

        if ($env) {
            $value = \preg_replace_callback('~\$\{([^}]*)\}~', function($m) use ($env) {
                return $env[$m[1]] ?? '';
            }, $value);
        }

        return $value;
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return self::CLASS;
    }
}
