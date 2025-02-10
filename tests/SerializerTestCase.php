<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Tests;

use PHPUnit\Framework\TestCase;
use RZ\TreeWalker\WalkerInterface;
use Symfony\Component\Serializer\Context\Normalizer\ObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializerTestCase extends TestCase
{
    protected function getSerializer(): Serializer
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $contextBuilder = (new ObjectNormalizerContextBuilder())
            ->withCircularReferenceHandler(function (WalkerInterface $object, string $format, array $context): string {
                return (string) $object->getItem();
            });

        return new Serializer([new ObjectNormalizer(classMetadataFactory: $classMetadataFactory, defaultContext: $contextBuilder->toArray())], [new JsonEncoder()]);
    }
}
