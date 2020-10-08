<?php declare(strict_types=1);
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Selector;

use Deployer\Host\Host;
use Deployer\Host\HostCollection;
use function Deployer\Support\array_all;

class Selector
{
    private $hosts;

    public function __construct(HostCollection $hosts)
    {
        $this->hosts = $hosts;
    }

    /**
     * @return Host[]
     */
    public function select(string $selectExpression)
    {
        $conditions = self::parse($selectExpression);

        $hosts = [];
        foreach ($this->hosts as $host) {
            if (self::apply($conditions, $host)) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    public static function apply($conditions, Host $host)
    {
        $labels = $host->get('labels', []);
        $labels['__host__'] = $host->getAlias();
        $labels['__all__'] = 'yes';
        $isTrue = function ($value) {
            return $value;
        };

        foreach ($conditions as $hmm) {
            $ok = [];
            foreach ($hmm as list($op, $var, $value)) {
                $ok[] = self::compare($op, $labels[$var] ?? null, $value);
            }
            if (count($ok) > 0 && array_all($ok, $isTrue)) {
                return true;
            }
        }
        return false;
    }

    private static function compare(string $op, $a, $b): bool
    {
        if ($op === '=') {
            return $a === $b;
        }
        if ($op === '!=') {
            return $a !== $b;
        }
        return false;
    }

    public static function parse(string $expression)
    {
        $all = [];
        foreach (explode(',', $expression) as $sub) {
            $conditions = [];
            foreach (explode('&', $sub) as $part) {
                $part = trim($part);
                if ($part === 'all') {
                    $conditions[] = ['=', '__all__', 'yes'];
                    continue;
                }
                if (preg_match('/(?<var>.+?)(?<op>!?=)(?<value>.+)/', $part, $match)) {
                    $conditions[] = [$match['op'], trim($match['var']), trim($match['value'])];
                } else {
                    $conditions[] = ['=', '__host__', trim($part)];
                }
            }
            $all[] = $conditions;
        }
        return $all;
    }
}