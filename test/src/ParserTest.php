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

use Bitnix\Parse\ParseFailure,
    PHPUnit\Framework\TestCase;

/**
 * @version 0.1.0
 */
class ParserTest extends TestCase {

    const FOO = 'foo allways bar';

    private $parser = null;

    public function setUp() : void {
        $this->parser = new Parser();
    }

    /**
     * @dataProvider invalid
     */
    public function testParseError(string $file) {
        $this->expectException(ParseFailure::CLASS);
        $this->parser->parse(\file_get_contents(__DIR__ . '/_env/' . $file));
    }

    public function invalid() : array {
        return [
            ['.env.error.01'],
            ['.env.error.02'],
            ['.env.error.03']
        ];
    }

    public function testParseEmpty() {
        $this->assertEquals([], $this->parser->parse(\file_get_contents(__DIR__ . '/_env/.env.empty')));
    }

    public function testParse() {
        $this->assertSame([
            'APP_NAME'        => 'foo',
            'APP_FOO'         => null,
            'APP_ZIG'         => 'zag zoe      ',
            'APP_ZAG'         => "zig \\\n              zoe",
            'APP_THIS'        => 'foobar',
            'APP_THAT'        => 'bar',
            'APP_WHATEVER'    => 'foobar/bar/baz',
            'APP_IGNORE'      => '${APP_NAME}bar',
            'APP_NULL'        => null,
            'APP_ON'          => true,
            'APP_YES'         => true,
            'APP_TRUE'        => true,
            'APP_OFF'         => false,
            'APP_NO'          => false,
            'APP_FALSE'       => false,
            'APP_INT'         => 1,
            'APP_FLOAT'       => -1.23,
            'APP_CONST'       => \PHP_INT_MAX,
            'APP_CLASS_CONST' => self::FOO,
            'APP_SQ_STRING'   => 'this is \'a string',
            'APP_DQ_STRING'   => "this is \na \"string\"",
            'APP_EXPORT'      => 'exported'
        ], $this->parser->parse(\file_get_contents(__DIR__ . '/_env/.env')));
    }

    public function testToString() {
        $this->assertIsString((string) $this->parser);
    }
}
