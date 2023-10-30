<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * (c) Simon Schaufelberger
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\Tscobj\Controller;

use Causal\Tscobj\Exception\ObjectNotFoundException;

class TypoScriptObjectController
{
    /**
     * The back-reference to the mother cObj object set at call time
     */
    public $cObj;

    public $prefixId = 'tx_tscobj_pi1';

    public $extKey = 'tscobj';

    /**
     * This setter is called when the plugin is called from UserContentObject (USER)
     * via ContentObjectRenderer->callUserFunction().
     *
     * @param ContentObjectRenderer $cObj
     */
    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * Returns the content object of the plugin.
     *
     * This function initialises the plugin 'tx_tscobj_pi1', and
     * launches the needed functions to correctly display the plugin.
     *
     * @param string $content The content object
     * @param array $conf The TS setup
     * @return string The content of the plugin
     */
    public function main(string $content, array $conf)
    {
        $this->conf = $conf;
        $this->pi_setPiVarDefaults();
        $this->pi_loadLL('EXT:tscobj/Resources/Private/Language/locallang.xlf');
        $this->pi_initPIflexForm();

        $typoScriptObjectPath = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'object');

        if (!$typoScriptObjectPath) {
            return  '';
        }

        $templatePath = explode('.', $typoScriptObjectPath);

        try {
            [$contentType, $typoScriptObject] = $this->validateTemplatePath($templatePath);
        } catch (ObjectNotFoundException $exception) {
            return '<strong>' . $this->pi_getLL('errors.notfound') . '</strong> (' . $typoScriptObjectPath . ')';
        }

        if (!isset($GLOBALS['TYPO3_CONF_VARS']['FE']['ContentObjects'][$contentType])) {
            // Invalid content type
            return '<strong>' . $this->pi_getLL('errors.invalid') . '</strong> (' . $contentType . ')';
        }

        $renderedObject = $this->cObj->cObjGetSingle($contentType, $typoScriptObject);

        return (bool)$this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'htmlspecialchars')
            ? nl2br(htmlspecialchars($renderedObject))
            : $renderedObject;
    }

    /**
     * @param array $templatePath
     * @return array
     * @throws ObjectNotFoundException
     */
    protected function validateTemplatePath(array $templatePath): array
    {
        $contentType = '';
        $typoScriptObject = $this->frontendController->tmpl->setup;

        $templatePaths = count($templatePath);
        for ($i = 0; $i < $templatePaths; $i++) {
            // Get content type
            $contentType = $typoScriptObject[$templatePath[$i]];

            // Get TS object configuration
            $typoScriptObject = $typoScriptObject[$templatePath[$i] . '.'];

            // Check object
            if (!$contentType && !$typoScriptObject) {
                throw new ObjectNotFoundException('');
            }
        }

        return [$contentType, $typoScriptObject];
    }

/* Converts $this->cObj->data['pi_flexform'] from XML string to FlexForm array.
*
* @param string $field Field name to convert
*/
    public function pi_initPIflexForm($field = 'pi_flexform')
    {
        // Converting flexform data into array
        $fieldData = $this->cObj->data[$field] ?? null;
        if (!is_array($fieldData) && $fieldData) {
            $this->cObj->data[$field] = GeneralUtility::xml2array((string)$fieldData);
            if (!is_array($this->cObj->data[$field])) {
                $this->cObj->data[$field] = [];
            }
        }
    }

    public function pi_getFFvalue(
        $T3FlexForm_array,
        $fieldName,
        $sheet = 'sDEF',
        $lang = 'lDEF',
        $value = 'vDEF'
    ) {
        $sheetArray = $T3FlexForm_array['data'][$sheet][$lang] ?? '';
        if (is_array($sheetArray)) {
            return $this->pi_getFFvalueFromSheetArray($sheetArray, explode('/', $fieldName), $value);
        }
        return null;
    }
}
