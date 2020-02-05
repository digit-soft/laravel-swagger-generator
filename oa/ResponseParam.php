<?php

namespace OA;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;

/**
 * Annotation can be used in `Response` as array of parameters.
 *
 * Example (`@` symbol omitted):
 * OA\Response({
 *      OA\ResponseParam("param_name_1", type="string", example="value", description="String parameter"),
 *      OA\ResponseParam("param_name_2", type="integer", example=1, description="Int parameter"),
 * })
 *
 * @Annotation
 * @Target({"ANNOTATION"})
 */
class ResponseParam extends Property
{
}
