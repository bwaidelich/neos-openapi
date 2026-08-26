<?php

declare(strict_types=1);

namespace Neos\OpenApi\Support;

/**
 * @see https://spec.openapis.org/oas/v3.1.1#style-values
 */
enum ParameterStyle: string
{
    case matrix = 'matrix';
    case label = 'label';
    case form = 'form';
    case simple = 'simple';
    case spaceDelimited = 'spaceDelimited';
    case pipeDelimited = 'pipeDelimited';
    case deepObject = 'deepObject';
}
