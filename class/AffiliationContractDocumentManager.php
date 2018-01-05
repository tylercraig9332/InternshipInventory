<?php
/**
 * This file is part of Internship Inventory.
 *
 * Internship Inventory is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.

 * Internship Inventory is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License version 3
 * along with Internship Inventory.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Copyright 2011-2018 Appalachian State University
 */

namespace Intern;

\PHPWS_Core::initModClass('filecabinet', 'Document_Manager.php');

/**
 * Document_Manager
 *
 * A subclass is needed because we need to do a little
 * extra work when a file is submitted. Also, the ID
 * of the new file is necessary to insert a new line in intern_agreement_documents.
 *
 * @author Chris Detsch
 */
class AffiliationContractDocumentManager extends \FC_Document_Manager {

    /**
     * @Override FC_Document_Manager::edit()
     *
     * This is a copy and paste of the overridden function
     * except that the module for the form is set to intern.
     * Also, check if the folder has been set. If not create
     * one for the user and load it.
     */
    public function edit()
    {

        if (empty($this->document)) {
            $this->loadDocument();
        }

        // If the folder ID is zero then it was not found
        // when Intern_Folder::documentUpload() was called.
        // Create one and load it.
        if ($this->folder->id == 0) {

            \PHPWS_Core::requireInc('filecabinet', 'defines.php');
            $folder = new AffiliateFolder();
            $folder->module_created = 'intern';
            $folder->title = 'affiliation agreements';
            $folder->public_folder = FALSE;
            $folder->ftype = DOCUMENT_FOLDER;
            $folder->loadDirectory();
            $folder->save();
            $this->folder = $folder;
        }

        \PHPWS_Core::initCoreClass('File.php');

        $form = new \PHPWS_FORM;
        $form->addHidden('module', 'intern');
        $form->addHidden('affiliate', $_REQUEST['affiliate']);
        $form->addHidden('action', 'postAffiliationUpload');
        $form->addHidden('ms', $this->max_size);
        $form->addHidden('folder_id', $this->folder->id);

        $form->addFile('file_name');
        $form->setSize('file_name', 30);
        $form->setLabel('file_name', dgettext('filecabinet', 'Document location'));

        $form->addText('title', $this->document->title);
        $form->setSize('title', 40);
        $form->setLabel('title', dgettext('filecabinet', 'Title'));

        $form->addTextArea('description', $this->document->description);
        $form->setLabel('description', dgettext('filecabinet', 'Description'));

        if ($this->document->id) {
            $form->addTplTag('FORM_TITLE', dgettext('filecabinet', 'Update file'));
            $form->addHidden('document_id', $this->document->id);
            $form->addSubmit('upload', dgettext('filecabinet', 'Update'));
        } else {
            $form->addTplTag('FORM_TITLE', dgettext('filecabinet', 'Upload new file'));
            $form->addSubmit('upload', dgettext('filecabinet', 'Upload'));
        }

        $form->addButton('cancel', dgettext('filecabinet', 'Cancel'));
        $form->setExtra('cancel', 'onclick="window.close()"');

        $form->setExtra('upload', 'onclick="this.style.display=\'none\'"');

        if ($this->document->id && \Current_User::allow('filecabinet', 'edit_folders', $this->folder->id, 'folder', true)) {
            \Cabinet::moveToForm($form, $this->folder);
        }

        $form->setMethod('post');
        $form->setAction('');

        $template = $form->getTemplate();

        if ($this->document->id) {
            $template['CURRENT_DOCUMENT_LABEL'] = dgettext('filecabinet', 'Current document');
            $template['CURRENT_DOCUMENT_ICON'] = $this->document->getIconView();
            $template['CURRENT_DOCUMENT_FILE'] = $this->document->file_name;
        }
        $template['MAX_SIZE_LABEL'] = dgettext('filecabinet', 'Maximum file size');

        $sys_size = str_replace('M', '', ini_get('upload_max_filesize'));

        $sys_size = $sys_size * 1000000;

        if ((int) $sys_size < (int) $this->max_size) {
            $template['MAX_SIZE'] = sprintf(dgettext('filecabinet', '%d bytes (system wide)'), $sys_size);
        } else {
            $template['MAX_SIZE'] = sprintf(dgettext('filecabinet', '%d bytes'), $this->max_size);
        }

        if ($this->document->_errors) {
            $template['ERROR'] = $this->document->printErrors();
        }
        return \PHPWS_Template::process($template, 'filecabinet', 'Forms/document_edit.tpl');
    }

    /**
     * @Override FC_Document_Manager::postDocumentUpload().
     *
     * This is a copy and past of the overriden function except
     * that we now create a new Intern_Document object
     * and save it to databse.
     */
    public function postDocumentUpload()
    {

        // importPost in File_Common
        $result = $this->document->importPost('file_name');

        if (\PEAR::isError($result) || !$result) {
            \PHPWS_Error::log($result);
            $vars = array();
            $vars['timeout'] = '3';
            $vars['refresh'] = 0;
            return dgettext('filecabinet', 'An error occurred when trying to save your document.');
        }

        $result = $this->document->save();


        if (\PHPWS_Error::logIfError($result)) {
            $content = dgettext('filecabinet', '<p>Could not upload file to folder. Please check your directory permissions.</p>');
            $content .= sprintf('<a href="#" onclick="window.close(); return false">%s</a>', dgettext('filecabinet', 'Close this window'));
            Layout::nakedDisplay($content);
            exit();
        }

        \PHPWS_Core::initModClass('filecabinet', 'File_Assoc.php');

        $this->document->moveToFolder();

            // Save Intern_Document in database.
            $doc = new AffiliationContract();
            $doc->agreement_id = $_REQUEST['affiliate'];
            $doc->document_id = $this->document->id;
            AffiliationContractFactory::save($doc);


        // Choose the proper notification text...
        if (isset($_REQUEST['document_id']) &&
                $_REQUEST['document_id'] && $result) {
            \NQ::simple('intern', \Intern\UI\NotifyUI::SUCCESS, "File saved.");
        } else if ($result) {
            \NQ::simple('intern', \Intern\UI\NotifyUI::SUCCESS, "File added.");
        } else if (PHPWS_Error::logIfError($result)) {
            \NQ::simple('intern', \Intern\UI\NotifyUI::ERROR, $result->toString());
        }
        \NQ::close();
        if (!isset($_POST['im'])) {
            javascript('close_refresh');
        } else {
            javascript('/filecabinet/refresh_manager', array('document_id' => $this->document->id));
        }
    }

}
