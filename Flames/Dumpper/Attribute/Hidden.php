<?php
declare(strict_types=1);


namespace Flames\Dumpper\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class Hidden
{
}