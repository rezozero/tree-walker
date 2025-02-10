<?php

declare(strict_types=1);

namespace RZ\TreeWalker\Serializer;

use RZ\TreeWalker\WalkerInterface;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class TreeWalkerNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    public function __construct(private readonly DenormalizerInterface&NormalizerInterface $decorated)
    {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if ($object instanceof WalkerInterface) {
            $type = explode('\\', get_class($object));
            /** @var array<string> $serializationGroups */
            $serializationGroups = isset($context['groups']) && is_array($context['groups']) ? $context['groups'] : [];
            $serialized = [
                '@type' => end($type),
                'item' => $this->decorated->normalize($object->getItem(), $format, $context),
            ];

            if (
                \in_array('children', $serializationGroups, true)
                && \in_array('walker_parent', $serializationGroups, true)
            ) {
                throw new \LogicException('You cannot use both "children" and "walker_parent" serialization groups at the same time.');
            }

            if (\in_array('children', $serializationGroups, true)) {
                $serialized['children'] = $object->getChildren()->map(function (mixed $walker) use ($format, $context) {
                    return $this->normalize($walker, $format, $context);
                })->getValues();
            }
            if (\in_array('walker_parent', $serializationGroups, true)) {
                $serialized['parent'] = $object->getParent();
            }
            if (\in_array('walker_level', $serializationGroups, true)) {
                $serialized['level'] = $object->getLevel();
            }
            if (\in_array('children_count', $serializationGroups, true)) {
                $serialized['childrenCount'] = $object->count();
            }
            if (\in_array('walker_metadata', $serializationGroups, true)) {
                $serialized['metadata'] = $object->getMetadata();
            }

            return $serialized;
        }

        return $this->decorated->normalize($object, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // Symfony 5.4 BC
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        // Symfony 5.4 BC
        return $this->decorated->supportsDenormalization($data, $type, $format);
    }

    /**
     * @param class-string $type
     *
     * @throws ExceptionInterface
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->decorated->denormalize($data, $type, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if ($this->decorated instanceof SerializerAwareInterface) {
            $this->decorated->setSerializer($serializer);
        }
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
    }
}
