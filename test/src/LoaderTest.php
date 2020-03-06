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

use PHPUnit\Framework\TestCase;

/**
 * @version 0.1.0
 */
class LoaderTest extends TestCase {

    private $loader = null;

    public function setUp() : void {
        $this->loader = new Loader();
    }

    public function testSyntaxError() {
        $this->expectException(LoadFailure::CLASS);
        $this->loader->require(__DIR__ . '/_env/.env.error.01');
    }

    public function testIncludeMissingFile() {
        $this->assertNull($this->loader->include(__DIR__ . '/_env/.not_env'));
    }

    public function testRequireMissingFile() {
        $this->expectException(LoadFailure::CLASS);
        $this->loader->require(__DIR__ . '/_env/.not_env');
    }

    public function testNonEmptyEnvFile() {
        $env = $this->loader->require(__DIR__ . '/_env/.env');
        $this->assertTrue(!empty($env));

        $env = $this->loader->include(__DIR__ . '/_env/.env');
        $this->assertTrue(!empty($env));
    }

    public function testEmptyEnvFile() {
        $this->assertEquals([], $this->loader->require(__DIR__ . '/_env/.env.empty'));
        $this->assertEquals([], $this->loader->include(__DIR__ . '/_env/.env.empty'));
    }

    public function testToString() {
        $this->assertIsString((string) $this->loader);
    }
}
