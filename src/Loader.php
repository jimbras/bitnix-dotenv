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

use Throwable,
    Bitnix\Parse\ParseFailure;

/**
 * @version 0.1.0
 */
final class Loader {

    /**
     * @var Parser
     */
    private ?Parser $parser = null;

    /**
     * @param string $file
     * @return array
     * @throws LoadFailure
     * @throws RuntimeException
     */
    public function require(string $file) : array {
        return $this->load($file);
    }

    /**
     * @param string $file
     * @return null|array
     * @throws LoadFailure
     * @throws RuntimeException
     */
    public function include(string $file) : ?array {
        return $this->load($file, false);
    }

    /**
     * @param string $file
     * @param bool $required
     * @return null|array
     * @throws LoadFailure
     * @throws RuntimeException
     */
    private function load(string $file, bool $required = true) : ?array {

        if (\is_file($file) && \is_readable($file)) {
            $file = \realpath($file);
            $contents = \trim(\file_get_contents($file));

            if ('' === $contents) {
                return [];
            }

            if (null === $this->parser) {
                $this->parser = new Parser();
            }

            try {
                return $this->parser->parse($contents);
            } catch (ParseFailure $pf) {
                throw new LoadFailure(\sprintf(
                    'Failed to parse env file "%s"%s%s',
                        $file,
                        \PHP_EOL,
                        (string) $pf
                ));
            }
        }

        if ($required) {
            throw new LoadFailure(\sprintf(
                'Unable to find dotenv file "%s"', $file
            ));
        }

        return null;
    }

    /**
     * @return string
     */
    public function __toString() : string {
        return self::CLASS;
    }
}
