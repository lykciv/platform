<?php

namespace Oro\Bundle\MessageQueueBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects message processors metadata for {@see \Oro\Component\MessageQueue\Client\Meta\TopicMetaRegistry}
 */
class BuildTopicMetaRegistryPass implements CompilerPassInterface
{
    use MessageProcessorsMetadataTrait;

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container): void
    {
        $metaRegistryId = 'oro_message_queue.client.meta.topic_meta_registry';
        $topicRegistryId = 'oro_message_queue.topic.registry';
        if (!$container->hasDefinition($metaRegistryId) || !$container->hasDefinition($topicRegistryId)) {
            return;
        }

        $topicServices = $this->findAndSortTaggedServices(
            new TaggedIteratorArgument('oro_message_queue.topic', 'topicName', 'getName', true),
            $container
        );

        $topicRegistry = $container->getDefinition($topicRegistryId);
        $topicRegistry->replaceArgument(0, new ServiceLocatorArgument($topicServices));

        $messageProcessorsMetadata = $this->findMessageProcessorsMetadata(
            $container,
            'oro_message_queue.client.message_processor'
        );

        $messageProcessorsByTopicAndQueue = [];
        $queuesByTopic = [];
        foreach ($messageProcessorsMetadata as [$serviceId, $topicName, $queueName]) {
            if (!array_key_exists($topicName, $topicServices)) {
                throw new \LogicException(
                    sprintf(
                        'Topic "%s" should be declared as MQ topic.',
                        $topicName
                    )
                );
            }
            if (isset($messageProcessorsByTopicAndQueue[$topicName][$queueName])) {
                throw new \LogicException(
                    sprintf(
                        'Service "%s" cannot be a message processor for the topic "%s" because '
                        . 'it is already claimed by "%s" service',
                        $serviceId,
                        $topicName,
                        $messageProcessorsByTopicAndQueue[$topicName][$queueName]
                    )
                );
            }

            $messageProcessorsByTopicAndQueue[$topicName][$queueName] = $serviceId;
            $queuesByTopic[$topicName][] = $queueName;
        }

        $container
            ->getDefinition($metaRegistryId)
            ->setArgument('$messageProcessorsByTopicAndQueue', $messageProcessorsByTopicAndQueue)
            ->setArgument('$queuesByTopic', $queuesByTopic);
    }
}
