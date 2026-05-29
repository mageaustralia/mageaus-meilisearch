<?php

/*
 * Subclass to be able to catch the error
 */
class Meilisearch_Search_Helper_Image extends Mage_Catalog_Helper_Image
{
    /**
     * The (string) cast invokes __toString(); the parent Mage_Catalog_Helper_Image::__toString()
     * is the DEFERRED path (returns a /core/index/resize signed URL when the file is not yet
     * cached), which the indexer would then freeze into the search index. Delegate to toString()
     * which force-generates the cache file and returns the STATIC, cacheable cache/ URL.
     */
    public function __toString(): string
    {
        try {
            return (string) $this->toString();
        } catch (\Throwable $e) {
            Mage::logException($e);
            return Mage::getDesign()->getSkinUrl($this->getPlaceholder());
        }
    }

    public function toString()
    {
        $model = $this->_getModel();

        if ($this->getImageFile()) {
            $model->setBaseFile($this->getImageFile());
        } else {
            $model->setBaseFile($this->getProduct()->getData($model->getDestinationSubdir()));
        }

        if ($model->isCached()) {
            return $this->removeProtocol($model->getUrl());
        }

        if ($this->_scheduleRotate) {
            $model->rotate($this->getAngle());
        }

        if ($this->_scheduleResize) {
            $model->resize();
        }

        if ($this->getWatermark()) {
            $model->setWatermark($this->getWatermark());
        }

        return $this->removeProtocol($model->saveFile()->getUrl());
    }

    public function removeProtocol($url)
    {
        return str_replace(['https://', 'http://'], '//', $url);
    }
}
