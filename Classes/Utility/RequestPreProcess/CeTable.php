<?php
namespace TYPO3\CMS\FrontendEditing\Utility\RequestPreProcess;

use TYPO3\CMS\FrontendEditing\Controller\SaveController;

/**
 * Hook for saving content element "table"
 */
class CeTable implements RequestPreProcessInterface
{

    /**
     * Pre process the request
     *
     * @param array $request save request
     * @param boolean $finished
     * @param \TYPO3\CMS\FrontendEditing\Controller\SaveController $parentObject
     * @return array
     */
    public function preProcess(array &$request, &$finished, SaveController &$parentObject)
    {
        $record = $parentObject->getRecord();

        // Only allowed for element "table"
        if ($parentObject->getTable() === 'tt_content'
            && $parentObject->getField() === 'bodytext'
            && $record['CType'] === 'table'
        ) {
            $finished = true;

            $domDocument = new \DOMDocument();
            $domDocument->loadHTML('<?xml encoding="utf-8" ?>' . $request['content']);

            $xPath = new \DOMXpath($domDocument);

            $trCollection = $xPath->query('//table/*/tr');
            $tmpCollection = array();
            if (!is_null($trCollection)) {
                foreach ($trCollection as $element) {
                    $singleLine = array();

                    $nodes = $element->childNodes;
                    foreach ($nodes as $node) {
                        $value = trim($node->nodeValue);
                        if (!empty($value)) {
                            $singleLine[] = $value;
                        }
                    }
                    $tmpCollection[] = implode('|', $singleLine);
                }
            }
            $request['content'] = implode(LF, $tmpCollection);

            $captionPath = $xPath->query('//table//caption[1]');
            $captionValue = '';
            foreach ($captionPath as $c) {
                $captionValue = trim($c->nodeValue);
            }

            $doc = new \DOMDOcument;
            $doc->loadxml($record['pi_flexform']);

            $replacement = $doc->createDocumentFragment();
            $replacement->appendXML('<value index="vDEF">' . $captionValue . '</value>');

            $xpath = new \DOMXpath($doc);

            $oldNode = $xpath->query('//field[@index=\'acctables_caption\']//value')->item(0);
            $oldNode->parentNode->replaceChild($replacement, $oldNode);
            $newPiFlexform = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>' .
                $doc->saveXml($doc->documentElement);

            $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                'tt_content',
                'uid=' . $record['uid'],
                ['pi_flexform' => $newPiFlexform]
            );
        }

        return $request;
    }
}
