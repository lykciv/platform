<?php

namespace Oro\Bundle\EmailBundle\Provider;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Inflector\Inflector;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Oro\Bundle\EntityBundle\Twig\Sandbox\EntityVariablesProviderInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\EntityPropertyInfo;
use Oro\Bundle\EntityExtendBundle\EntityReflectionClass;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\UIBundle\Formatter\FormatterManager;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The provider that collects variables from ORM metadata and entity configs
 * for fields marked as "available_in_template".
 */
class EntityVariablesProvider implements EntityVariablesProviderInterface
{
    /** @var TranslatorInterface */
    protected $translator;

    /** @var ConfigManager */
    protected $configManager;

    /** @var FormatterManager */
    protected $formatterManager;

    /** @var ManagerRegistry */
    protected $doctrine;
    private Inflector $inflector;

    public function __construct(
        TranslatorInterface $translator,
        ConfigManager $configManager,
        ManagerRegistry $doctrine,
        FormatterManager $formatterManager,
        Inflector $inflector
    ) {
        $this->translator = $translator;
        $this->configManager = $configManager;
        $this->doctrine = $doctrine;
        $this->formatterManager = $formatterManager;
        $this->inflector = $inflector;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariableDefinitions(): array
    {
        $result = [];
        $entityIds = $this->configManager->getProvider('entity')->getIds();
        foreach ($entityIds as $entityId) {
            $className = $entityId->getClassName();
            $entityData = $this->getEntityVariableDefinitions($className);
            if (!empty($entityData)) {
                $result[$className] = $entityData;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariableGetters(): array
    {
        $result = [];
        $entityIds = $this->configManager->getProvider('entity')->getIds();
        foreach ($entityIds as $entityId) {
            $className = $entityId->getClassName();
            $entityData = $this->getEntityVariableGetters($className);
            if (!empty($entityData)) {
                $result[$className] = $entityData;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getVariableProcessors(string $entityClass): array
    {
        return [];
    }

    /**
     * @param string $entityClass
     *
     * @return array
     */
    protected function getEntityVariableDefinitions($entityClass)
    {
        $entityClass = ClassUtils::getRealClass($entityClass);
        $extendConfigProvider = $this->configManager->getProvider('extend');
        if (!$extendConfigProvider->hasConfig($entityClass)
            || !ExtendHelper::isEntityAccessible($extendConfigProvider->getConfig($entityClass))
        ) {
            return [];
        }

        $result = [];

        $em = $this->doctrine->getManagerForClass($entityClass);
        $metadata = $em->getClassMetadata($entityClass);
        $reflClass = new EntityReflectionClass($entityClass);
        $entityConfigProvider = $this->configManager->getProvider('entity');
        $fieldConfigs = $this->configManager->getProvider('email')->getConfigs($entityClass);
        foreach ($fieldConfigs as $fieldConfig) {
            if (!$fieldConfig->is('available_in_template')) {
                continue;
            }

            /** @var FieldConfigId $fieldId */
            $fieldId = $fieldConfig->getId();
            $fieldName = $fieldId->getFieldName();

            list($varName) = $this->getFieldAccessInfo($reflClass, $fieldName);
            if (!$varName) {
                continue;
            }

            $fieldLabel = (string) $entityConfigProvider->getConfig($entityClass, $fieldName)->get('label');

            $var = [
                'type'  => $fieldId->getFieldType(),
                'label' => $this->translator->trans($fieldLabel)
            ];

            $this->addFormatterData($fieldId, $var);
            $this->addTargetClassData($fieldId, $metadata, $var);
            if (!empty($var['related_entity_name']) && !$entityConfigProvider->hasConfig($var['related_entity_name'])) {
                unset($var['related_entity_name']);
            }

            $result[$varName] = $var;
        }

        return $result;
    }

    /**
     * @param string $entityClass
     *
     * @return array
     */
    protected function getEntityVariableGetters($entityClass)
    {
        $entityClass = ClassUtils::getRealClass($entityClass);
        $extendConfigProvider = $this->configManager->getProvider('extend');
        if (!$extendConfigProvider->hasConfig($entityClass)
            || !ExtendHelper::isEntityAccessible($extendConfigProvider->getConfig($entityClass))
        ) {
            return [];
        }

        $em = $this->doctrine->getManagerForClass($entityClass);
        $metadata = $em->getClassMetadata($entityClass);
        $result = [];
        $reflClass = new EntityReflectionClass($entityClass);
        $fieldConfigs = $this->configManager->getProvider('email')->getConfigs($entityClass);
        foreach ($fieldConfigs as $fieldConfig) {
            if (!$fieldConfig->is('available_in_template')) {
                continue;
            }

            /** @var FieldConfigId $fieldId */
            $fieldId = $fieldConfig->getId();

            [$varName, $getter] = $this->getFieldAccessInfo($reflClass, $fieldId->getFieldName());
            if (!$varName) {
                continue;
            }

            $data = [];
            $this->addTargetClassData($fieldId, $metadata, $data);
            $this->addFormatterData($fieldId, $data);
            if ($data) {
                $getter = array_merge($data, ['property_path' => $getter]);
            }

            $result[$varName] = $getter;
        }
        $extendEntityMethods = EntityPropertyInfo::getExtendedMethods($entityClass);
        $result = array_merge($result, array_keys($extendEntityMethods));

        return $result;
    }

    /**
     * @param \ReflectionClass $reflClass
     * @param string           $fieldName
     *
     * @return array [variable name, getter method name]
     */
    protected function getFieldAccessInfo(\ReflectionClass $reflClass, $fieldName)
    {
        $getter = null;
        if ($reflClass->hasProperty($fieldName) && $reflClass->getProperty($fieldName)->isPublic()) {
            return [$fieldName, null];
        }

        $name = $this->inflector->classify($fieldName);
        $getter = 'get' . $name;
        if ($reflClass->hasMethod($getter) && $reflClass->getMethod($getter)->isPublic()) {
            return [lcfirst($name), $getter];
        }

        $getter = 'is' . $name;
        if ($reflClass->hasMethod($getter) && $reflClass->getMethod($getter)->isPublic()) {
            return [lcfirst($name), $getter];
        }

        return [null, null];
    }

    private function addTargetClassData(FieldConfigId $fieldId, ClassMetadata $metadata, array &$data)
    {
        $fieldName = $fieldId->getFieldName();
        if ($metadata->hasAssociation($fieldName)) {
            $data['related_entity_name'] = $metadata->getAssociationTargetClass($fieldName);
        }
    }

    private function addFormatterData(FieldConfigId $fieldId, array &$data)
    {
        $formatter = $this->formatterManager->guessFormatter($fieldId->getFieldType());
        if ($formatter) {
            $data['default_formatter'] = $formatter;
        }
    }
}
