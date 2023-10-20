<?php
/*
 * Copyright © Kiliba. All rights reserved.
 */

namespace Kiliba\Connector\Model\Import;

class FormatterResolver
{
    /**
     * Catalog view layer models list
     *
     * @var array
     */
    protected $formatterPool;

    /**
     * Filter factory
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Catalog\Model\Layer
     */
    protected $formatter = null;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array $formatterPool
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $formatterPool
    ) {
        $this->objectManager = $objectManager;
        $this->formatterPool = $formatterPool;
    }

    public function create($layerType)
    {
        if (isset($this->formatter[$layerType])) {
            throw new \RuntimeException('Import formatter has been already created');
        }
        if (!isset($this->formatterPool[$layerType])) {
            throw new \InvalidArgumentException($layerType . ' does not belong to any registered formatter');
        }
        $this->formatter[$layerType] = $this->objectManager->create($this->formatterPool[$layerType])->create();
    }

    public function get($layerType)
    {
        if (!isset($this->formatter[$layerType])) {
            $this->create($layerType);
        }
        return $this->formatter[$layerType];
    }
}
