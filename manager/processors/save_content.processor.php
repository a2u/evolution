<?php
if( ! defined('IN_MANAGER_MODE') || IN_MANAGER_MODE !== true) {
    die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the EVO Content Manager instead of accessing this file directly.");
}
if (!$modx->hasPermission('save_document')) {
    $modx->webAlertAndQuit($_lang["error_no_privileges"]);
}

// preprocess POST values
$id = is_numeric($_POST['id']) ? $_POST['id'] : '';

$introtext = $_POST['introtext'];
$content = $_POST['ta'];
$pagetitle = $_POST['pagetitle'];
$description = $_POST['description'];
$alias = $_POST['alias'];
$link_attributes = $_POST['link_attributes'];
$isfolder = (int)$_POST['isfolder'];
$richtext = (int)$_POST['richtext'];
$published = (int)$_POST['published'];
$parent = (int)get_by_key($_POST, 'parent', 0, 'is_scalar');
$template = (int)$_POST['template'];
$menuindex = !empty($_POST['menuindex']) ? (int)$_POST['menuindex'] : 0;
$searchable = (int)$_POST['searchable'];
$cacheable = (int)$_POST['cacheable'];
$syncsite = (int)$_POST['syncsite'];
$pub_date = $_POST['pub_date'];
$unpub_date = $_POST['unpub_date'];
$document_groups = (isset($_POST['chkalldocs']) && $_POST['chkalldocs'] == 'on') ? [] : get_by_key($_POST, 'docgroups', [], 'is_array');
$type = $_POST['type'];
$contentType = $_POST['contentType'];
$contentdispo = (int)$_POST['content_dispo'];
$longtitle = $_POST['longtitle'];
$donthit = (int)$_POST['donthit'];
$menutitle = $_POST['menutitle'];
$hidemenu = (int)$_POST['hidemenu'];
$aliasvisible = (int)$_POST['alias_visible'];

/************* webber ********/
$sd=isset($_POST['dir']) && strtolower($_POST['dir']) === 'asc' ? '&dir=ASC' : '&dir=DESC';
$sb=isset($_POST['sort'])?'&sort='.entities($_POST['sort'], $modx->getConfig('modx_charset')):'&sort=pub_date';
$pg=isset($_POST['page'])?'&page='.(int)$_POST['page']:'';
$add_path=$sd.$sb.$pg;



$no_esc_pagetitle = $_POST['pagetitle'];
if (trim($no_esc_pagetitle) == "") {
    if ($type == "reference") {
        $no_esc_pagetitle = $pagetitle = $_lang['untitled_weblink'];
    } else {
        $no_esc_pagetitle = $pagetitle = $_lang['untitled_resource'];
    }
}

// get table names
$tbl_document_groups            = $modx->getDatabase()->getFullTableName('document_groups');
$tbl_documentgroup_names        = $modx->getDatabase()->getFullTableName('documentgroup_names');
$tbl_member_groups              = $modx->getDatabase()->getFullTableName('member_groups');
$tbl_membergroup_access         = $modx->getDatabase()->getFullTableName('membergroup_access');
$tbl_site_content               = $modx->getDatabase()->getFullTableName('site_content');
$tbl_site_tmplvar_access        = $modx->getDatabase()->getFullTableName('site_tmplvar_access');
$tbl_site_tmplvar_contentvalues = $modx->getDatabase()->getFullTableName('site_tmplvar_contentvalues');

$actionToTake = "new";
if ($_POST['mode'] == '73' || $_POST['mode'] == '27') {
    $actionToTake = "edit";
}

// friendly url alias checks
if ($modx->getConfig('friendly_urls')) {
    // auto assign alias
    if (!$alias && $modx->getConfig('automatic_alias')) {
        $alias = strtolower($modx->stripAlias(trim($pagetitle)));
        if(!$modx->getConfig('allow_duplicate_alias')) {

            if (\EvolutionCMS\Models\SiteContent::query()
                    ->where('id', '<>', $id)
                    ->where('alias', $alias)->count() > 0) {
                $cnt = 1;
                $tempAlias = $alias;
                while (\EvolutionCMS\Models\SiteContent::query()
                    ->where('id', '<>', $id)
                    ->where('alias', $alias)->count() > 0) {
                    $tempAlias = $alias;
                    $tempAlias .= $cnt;
                    $cnt++;
                }
                $alias = $tempAlias;
            }
        }else{
            if (\EvolutionCMS\Models\SiteContent::query()
                    ->where('id', '<>', $id)
                    ->where('alias', $alias)
                    ->where('parent', $parent)->count() > 0) {
                $cnt = 1;
                $tempAlias = $alias;
                while (\EvolutionCMS\Models\SiteContent::query()
                        ->where('id', '<>', $id)
                        ->where('alias', $alias)
                        ->where('parent', $parent)->count() > 0) {
                    $tempAlias = $alias;
                    $tempAlias .= $cnt;
                    $cnt++;
                }
                $alias = $tempAlias;
            }
        }
    }

    // check for duplicate alias name if not allowed
    elseif ($alias && !$modx->getConfig('allow_duplicate_alias')) {
        $alias = $modx->stripAlias($alias);
        $docid = \EvolutionCMS\Models\SiteContent::query()->select('id')
            ->where('id', '<>', $id)
            ->where('alias', $alias);
        if ($modx->getConfig('use_alias_path')) {
            // only check for duplicates on the same level if alias_path is on
            $docid = $docid->where('parent', $parent);
        }
        $docid = $docid->first();
        if (!is_null($docid)) {
            if ($actionToTake == 'edit') {
                $modx->getManagerApi()->saveFormValues(27);
                $modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $docid->id, $alias), "index.php?a=27&id={$id}");
            } else {
                $modx->getManagerApi()->saveFormValues(4);
                $modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $docid->id, $alias), "index.php?a=4");
            }
        }
    }

    // strip alias of special characters
    elseif ($alias) {
        $alias = $modx->stripAlias($alias);
        $docid = \EvolutionCMS\Models\SiteContent::query()->select('id')
            ->where('id', '<>', $id)
            ->where('alias', $alias)->where('parent', $parent)->first();
        if (!is_null($docid)) {
            if ($actionToTake == 'edit') {
                $modx->getManagerApi()->saveFormValues(27);
                $modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $docid->id, $alias), "index.php?a=27&id={$id}");
            } else {
                $modx->getManagerApi()->saveFormValues(4);
                $modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $docid->id, $alias), "index.php?a=4");
            }
        }
    }
}
elseif ($alias) {
    $alias = $modx->stripAlias($alias);
}

// determine published status
$currentdate = $modx->timestamp((int)get_by_key($_SERVER, 'REQUEST_TIME', 0));

if (empty ($pub_date)) {
    $pub_date = 0;
} else {
    $pub_date = $modx->toTimeStamp($pub_date);

    if ($pub_date < $currentdate) {
        $published = 1;
    }
    elseif ($pub_date > $currentdate) {
        $published = 0;
    }
}

if (empty ($unpub_date)) {
    $unpub_date = 0;
} else {
    $unpub_date = $modx->toTimeStamp($unpub_date);
    if ($unpub_date < $currentdate) {
        $published = 0;
    }
}

// get document groups for current user
$tmplvars = array ();
$docgrp = $_SESSION['mgrDocgroups'] ? implode(",", $_SESSION['mgrDocgroups']) : '';

// ensure that user has not made this document inaccessible to themselves
if($_SESSION['mgrRole'] != 1 && is_array($document_groups)) {
    $document_group_list = implode(',', $document_groups);
    $document_group_list = implode(',', array_filter(explode(',',$document_group_list), 'is_numeric'));
    if(!empty($document_group_list)) {
        $rs = $modx->getDatabase()->select('COUNT(mg.id)', "{$tbl_membergroup_access} AS mga, {$tbl_member_groups} AS mg", "mga.membergroup = mg.user_group AND mga.documentgroup IN({$document_group_list}) AND mg.member = {$_SESSION['mgrInternalKey']}");
        $count = $modx->getDatabase()->getValue($rs);
        if($count == 0) {
            if ($actionToTake == 'edit') {
                $modx->getManagerApi()->saveFormValues(27);
                $modx->webAlertAndQuit(sprintf($_lang["resource_permissions_error"]), "index.php?a=27&id={$id}");
            } else {
                $modx->getManagerApi()->saveFormValues(4);
                $modx->webAlertAndQuit(sprintf($_lang["resource_permissions_error"]), "index.php?a=4");
            }
        }
    }
}

$tvs = \EvolutionCMS\Models\SiteTmplvar::query()->distinct()
    ->select('site_tmplvars.*', 'site_tmplvar_contentvalues.value')
    ->join('site_tmplvar_templates', 'site_tmplvar_templates.tmplvarid', '=', 'site_tmplvars.id')
    ->leftJoin('site_tmplvar_contentvalues', function ($join) use ($id) {
        $join->on('site_tmplvar_contentvalues.tmplvarid', '=', 'site_tmplvars.id');
        $join->on('site_tmplvar_contentvalues.contentid', '=', \DB::raw($id));
    })->join('site_tmplvar_access', 'site_tmplvar_access.tmplvarid', '=', 'site_tmplvars.id')
    ->where('site_tmplvar_templates.templateid', $template)->orderBy('site_tmplvars.rank');
if($_SESSION['mgrRole']!= 1){
    $tvs = $tvs->where(function ($query) {
        $query->whereNull('site_tmplvar_access.documentgroup')
            ->orWhereIn('document_groups.document_group', $_SESSION['mgrDocgroups']);
    });
}
$tvs = $tvs->get();

foreach ($tvs->toArray() as $row) {
    $tmplvar = '';
    switch ($row['type']) {
        case 'url':
            $tmplvar = $_POST["tv" . $row['id']];
            if ($_POST["tv" . $row['id'] . '_prefix'] != '--') {
                $tmplvar = str_replace(array (
                    "feed://",
                    "ftp://",
                    "http://",
                    "https://",
                    "mailto:"
                ), "", $tmplvar);
                $tmplvar = $_POST["tv" . $row['id'] . '_prefix'] . $tmplvar;
            }
        break;
        case 'file':
            $tmplvar = $_POST["tv" . $row['id']];
        break;
        default:
            $tmp = get_by_key($_POST, 'tv' . $row['id']);
            if (is_array($tmp)) {
                // handles checkboxes & multiple selects elements
                $feature_insert = [];
                foreach ($tmp as $featureValue => $feature_item) {
                    $feature_insert[count($feature_insert)] = $feature_item;
                }
                $tmplvar = implode("||", $feature_insert);
            } else {
                $tmplvar = $tmp;
            }
        break;
    }
    // save value if it was modified
    if (strlen($tmplvar) > 0 && $tmplvar != $row['default_text']) {
        $tmplvars[$row['id']] = array (
            $row['id'],
            $tmplvar
        );
    } else {
        // Mark the variable for deletion
        $tmplvars[$row['name']] = $row['id'];
    }
}

// get the document, but only if it already exists
if ($actionToTake != "new") {
    $existingDocument = \EvolutionCMS\Models\SiteContent::query()->find($id);
    if (is_null($existingDocument)) {
        $modx->webAlertAndQuit($_lang["error_no_results"]);
    }
    $existingDocument = $existingDocument->toArray();
}

// check to see if the user is allowed to save the document in the place he wants to save it in
if ($modx->getConfig('use_udperms') == 1) {
    if ($existingDocument['parent'] != $parent) {
        $udperms = new EvolutionCMS\Legacy\Permissions();
        $udperms->user = $modx->getLoginUserID('mgr');
        $udperms->document = $parent;
        $udperms->role = $_SESSION['mgrRole'];

        if (!$udperms->checkPermissions()) {
            if ($actionToTake == 'edit') {
                $modx->getManagerApi()->saveFormValues(27);
                $modx->webAlertAndQuit(sprintf($_lang['access_permission_parent_denied'], $docid, $alias), "index.php?a=27&id={$id}");
            } else {
                $modx->getManagerApi()->saveFormValues(4);
                $modx->webAlertAndQuit(sprintf($_lang['access_permission_parent_denied'], $docid, $alias), "index.php?a=4");
            }
        }
    }
}

$resourceArray = array
(
    "introtext"        => $introtext ,
    "content"          => $content ,
    "pagetitle"        => $pagetitle ,
    "longtitle"        => $longtitle ,
    "type"             => $type ,
    "description"      => $description ,
    "alias"            => $alias ,
    "link_attributes"  => $link_attributes ,
    "isfolder"         => $isfolder ,
    "richtext"         => $richtext ,
    "published"        => $published ,
    "parent"           => $parent ,
    "template"         => $template ,
    "menuindex"        => $menuindex ,
    "searchable"       => $searchable ,
    "cacheable"        => $cacheable ,
    "editedby"         => $modx->getLoginUserID('mgr') ,
    "editedon"         => $currentdate ,
    "pub_date"         => $pub_date ,
    "unpub_date"       => $unpub_date ,
    "contentType"      => $contentType ,
    "content_dispo"    => $contentdispo ,
    "donthit"          => $donthit ,
    "menutitle"        => $menutitle ,
    "hidemenu"         => $hidemenu ,
    "alias_visible"    => $aliasvisible
);

switch ($actionToTake) {
        case 'new' :
            $resourceArray['createdby'] = $modx->getLoginUserID('mgr');
            $resourceArray['createdon'] = $currentdate;
            // invoke OnBeforeDocFormSave event
            switch($modx->config['docid_incrmnt_method'])
            {
            case '1':
                $from = "{$tbl_site_content} AS T0 LEFT JOIN {$tbl_site_content} AS T1 ON T0.id + 1 = T1.id";
                $where = "T1.id IS NULL";
                $rs = $modx->getDatabase()->select('MIN(T0.id)+1', $from, "T1.id IS NULL");
                $id = $modx->getDatabase()->getValue($rs);
                break;
            case '2':
                $rs = $modx->getDatabase()->select('MAX(id)+1', $tbl_site_content);
                $id = $modx->getDatabase()->getValue($rs);
            break;

            default:
                $id = '';
            }

        $modx->invokeEvent("OnBeforeDocFormSave", array (
            "mode" => "new",
            "id" => $id
        ));

        // deny publishing if not permitted
        if (!$modx->hasPermission('publish_document')) {
            $pub_date = 0;
            $unpub_date = 0;
            $published = 0;
        }

        $publishedon = ($published ? $currentdate : 0);
        $publishedby = ($published ? $modx->getLoginUserID('mgr') : 0);

        if ((!empty($pub_date))&&($published)){
            $publishedon=$pub_date;
        }


        $resourceArray['pub_date'] = $pub_date;
        $resourceArray['publishedon'] = $publishedon;
        $resourceArray['publishedby'] = $publishedby;
        $resourceArray['unpub_date'] = $unpub_date;

        if ($id != '')
            $dbInsert["id"] = $id;

        $key = $modx->getDatabase()->insert($dbInsert, $tbl_site_content);

        $tvChanges = array();
        foreach ($tmplvars as $field => $value) {
            if (is_array($value)) {
                $tvId = $value[0];
                $tvVal = $value[1];
                $tvChanges[] = array('tmplvarid' => $tvId, 'contentid' => $key, 'value' => $modx->getDatabase()->escape($tvVal));
            }
        }
        if (!empty($tvChanges)) {
            foreach ($tvChanges as $tv) {
                $modx->getDatabase()->insert($tv, $tbl_site_tmplvar_contentvalues);
            }
        }

        // document access permissions
        if ($modx->getConfig('use_udperms') == 1 && is_array($document_groups)) {
            $new_groups = array();
            foreach ($document_groups as $value_pair) {
                // first, split the pair (this is a new document, so ignore the second value
                list($group) = explode(',', $value_pair); // @see actions/mutate_content.dynamic.php @ line 1138 (permissions list)
                $new_groups[] = '('.(int)$group.','.$key.')';
            }
            $saved = true;
            if (!empty($new_groups)) {
                $modx->getDatabase()->query("INSERT INTO {$tbl_document_groups} (document_group, document) VALUES ".implode(',', $new_groups));
            }
        } else {
            $isManager = $modx->hasPermission('access_permissions');
            $isWeb     = $modx->hasPermission('web_access_permissions');
            if($modx->getConfig('use_udperms') && !($isManager || $isWeb) && $parent != 0) {
                // inherit document access permissions
                $modx->getDatabase()->insert(
                    array(
                        'document_group' =>'',
                        'document'       =>''
                        ), $tbl_document_groups, // Insert into
                    "document_group, {$key}", $tbl_document_groups, "document = '{$parent}'"); // Copy from
            }
        }


        // update parent folder status
        if ($parent != 0) {
            $fields = array('isfolder' => 1);
            $modx->getDatabase()->update($fields, $tbl_site_content, "id='{$_REQUEST['parent']}'");
        }

        // invoke OnDocFormSave event
        $modx->invokeEvent("OnDocFormSave", array (
            "mode" => "new",
            "id" => $key
        ));

        // secure web documents - flag as private
        include MODX_MANAGER_PATH . "includes/secure_web_documents.inc.php";
        secureWebDocument($key);

        // secure manager documents - flag as private
        include MODX_MANAGER_PATH . "includes/secure_mgr_documents.inc.php";
        secureMgrDocument($key);

        // Set the item name for logger
        $_SESSION['itemname'] = $no_esc_pagetitle;

        if ($syncsite == 1) {
            // empty cache
            $modx->clearCache('full');
        }

        // redirect/stay options
        if ($_POST['stay'] != '') {
            // weblink
            if ($_POST['mode'] == "72")
                $a = ($_POST['stay'] == '2') ? "27&id=$key" : "72&pid=$parent";
            // document
            if ($_POST['mode'] == "4")
                $a = ($_POST['stay'] == '2') ? "27&id=$key" : "4&pid=$parent";
            $header = "Location: index.php?a=" . $a . "&r=1&stay=" . $_POST['stay'];
        } else {
            $header = "Location: index.php?a=3&id=$key&r=1";
        }

        if (headers_sent()) {
            $header = str_replace('Location: ','',$header);
            echo "<script>document.location.href='$header';</script>\n";
        } else {
            header($header);
        }


        break;
        case 'edit' :
            // get the document's current parent
            $oldparent = $existingDocument['parent'];
            $doctype = $existingDocument['type'];

            if ($id == $modx->getConfig('site_start') && $published == 0) {
                $modx->getManagerApi()->saveFormValues(27);
                $modx->webAlertAndQuit("Document is linked to site_start variable and cannot be unpublished!");
            }
            $today = $modx->timestamp((int)get_by_key($_SERVER, 'REQUEST_TIME', 0));
            if ($id == $modx->getConfig('site_start') && ($pub_date > $today || $unpub_date != "0")) {
                $modx->getManagerApi()->saveFormValues(27);
                $modx->webAlertAndQuit("Document is linked to site_start variable and cannot have publish or unpublish dates set!");
            }
            if ($parent == $id) {
                $modx->getManagerApi()->saveFormValues(27);
                $modx->webAlertAndQuit("Document can not be it's own parent!");
            }

            $parents = $modx->getParentIds($parent);
            if (in_array($id, $parents)) {
                $modx->webAlertAndQuit("Document descendant can not be it's parent!");
            }

            // check to see document is a folder
            $child = \EvolutionCMS\Models\SiteContent::select('id')->where('parent', $id)->first();
            if (!is_null($child)) {
                $isfolder = 1;
            }

            // set publishedon and publishedby
            $was_published = $existingDocument['published'];

            // keep original publish state, if change is not permitted
            if (!$modx->hasPermission('publish_document')) {
                $published = $was_published;
                $pub_date = 'pub_date';
                $unpub_date = 'unpub_date';
            }

            // if it was changed from unpublished to published
            if (!$was_published && $published) {
                $publishedon = $currentdate;
                $publishedby = $modx->getLoginUserID('mgr');
                }elseif ((!empty($pub_date)&& $pub_date<=$currentdate && $published)) {
                $publishedon = $pub_date;
                $publishedby = $modx->getLoginUserID('mgr');
                   }elseif ($was_published && !$published) {
                $publishedon = 0;
                $publishedby = 0;
            } else {
                $publishedon = $existingDocument['publishedon'];
                $publishedby = $existingDocument['publishedby'];
            }

            $resourceArray['pub_date'] = $pub_date;
            $resourceArray['publishedon'] = $publishedon;
            $resourceArray['publishedby'] = $publishedby;

            // invoke OnBeforeDocFormSave event
            $modx->invokeEvent("OnBeforeDocFormSave", array (
                "mode" => "upd",
                "id" => $id
            ));

            \EvolutionCMS\Models\SiteContent::query()->find($id)->update($resourceArray);

            // update template variables
            $tvs = \EvolutionCMS\Models\SiteTmplvarContentvalue::select('id', 'tmplvarid')->where('contentid', $id)->get();
            $tvIds = array ();
            foreach ($tvs as $tv) {
                $tvIds[$tv->tmplvarid] = $tv->id;
            }
            $tvDeletions = array();
            $tvChanges = array();
            $tvAdded = array();

            foreach ($tmplvars as $field => $value) {
                if (!is_array($value)) {
                    if (isset($tvIds[$value])) $tvDeletions[] = $tvIds[$value];
                } else {
                    $tvId = $value[0];
                    $tvVal = $value[1];
                    if (isset($tvIds[$tvId])) {
                        \EvolutionCMS\Models\SiteTmplvarContentvalue::query()->find($tvIds[$tvId])->update(array('tmplvarid' => $tvId, 'contentid' => $id, 'value' => $tvVal));
                    } else {
                        \EvolutionCMS\Models\SiteTmplvarContentvalue::query()->create(array('tmplvarid' => $tvId, 'contentid' => $id, 'value' => $tvVal));
                    }
                }
            }

            if (!empty($tvDeletions)) {
                \EvolutionCMS\Models\SiteTmplvarContentvalue::query()->whereIn('id', $tvDeletions)->delete();
            }

            // set document permissions
            if ($modx->getConfig('use_udperms') == 1 && is_array($document_groups)) {
                $new_groups = array();
                // process the new input
                foreach ($document_groups as $value_pair) {
                    list($group, $link_id) = explode(',', $value_pair); // @see actions/mutate_content.dynamic.php @ line 1138 (permissions list)
                    $new_groups[$group] = $link_id;
                }

                // grab the current set of permissions on this document the user can access
                $isManager = $modx->hasPermission('access_permissions');
                $isWeb     = $modx->hasPermission('web_access_permissions');
                $documentGroups = \EvolutionCMS\Models\DocumentGroup::query()->select('document_groups.id','document_groups.document_group')
                    ->leftJoin('documentgroup_names','document_groups.document_group','=','documentgroup_names.id')
                    ->where(function ($query) use ($isWeb, $isManager) {
                        $query->where(function ($query) use ($isManager) {
                            $query->whereRaw('1 = '.(int)$isManager)
                                ->where('documentgroup_names.private_memgroup', true);
                        })
                            ->orWhere(function ($query) use ($isWeb) {
                                $query->whereRaw('1 = '.(int)$isWeb)
                                    ->where('documentgroup_names.private_webgroup', true);
                            });
                    })->where('document_groups.document', $id)->get();

                $old_groups = array();
                foreach ($documentGroups as $documentGroup)
                    $old_groups[$documentGroup->document_group] = $documentGroup->id;

                // update the permissions in the database
                $insertions = $deletions = array();
                foreach ($new_groups as $group => $link_id) {
                    if (array_key_exists($group, $old_groups)) {
                        unset($old_groups[$group]);
                        continue;
                    } elseif ($link_id == 'new') {
                        $insertions[] = ['document_group'=>(int)$group, 'document'=>$id];
                    }
                }
                if (!empty($insertions)) {
                    \EvolutionCMS\Models\DocumentGroup::query()->insert($insertions);
                }
                if (!empty($old_groups)) {
                    \EvolutionCMS\Models\DocumentGroup::query()->whereIn('id', $old_groups)->delete();
                }
                // necessary to remove all permissions as document is public
                if ((isset($_POST['chkalldocs']) && $_POST['chkalldocs'] == 'on')) {
                    \EvolutionCMS\Models\DocumentGroup::query()->where('document', $id)->delete();
                }
            }

            // do the parent stuff
            if ($parent != 0) {
                \EvolutionCMS\Models\SiteContent::find($_REQUEST['parent'])->update(array('isfolder' => 1));
            }

            // finished moving the document, now check to see if the old_parent should no longer be a folder
            $countChildOldParent = \EvolutionCMS\Models\SiteContent::where('parent', $oldparent)->count();

            if ($countChildOldParent == 0) {
                \EvolutionCMS\Models\SiteContent::find($_REQUEST['parent'])->update(array('isfolder' => 0));
            }


            // invoke OnDocFormSave event
            $modx->invokeEvent("OnDocFormSave", array (
                "mode" => "upd",
                "id" => $id
            ));

            // secure web documents - flag as private
            include MODX_MANAGER_PATH . "includes/secure_web_documents.inc.php";
            secureWebDocument($id);

            // secure manager documents - flag as private
            include MODX_MANAGER_PATH . "includes/secure_mgr_documents.inc.php";
            secureMgrDocument($id);

            // Set the item name for logger
            $_SESSION['itemname'] = $no_esc_pagetitle;

            if ($syncsite == 1) {
                // empty cache
                $modx->clearCache('full');
            }

            if ($_POST['refresh_preview'] == '1')
                $header = "Location: ".MODX_SITE_URL."index.php?id=$id&z=manprev";
            else {
                if ($_POST['stay'] != '2' && $id > 0) {
                    $modx->unlockElement(7, $id);
                }
                if ($_POST['stay'] != '') {
                    $id = $_REQUEST['id'];
                    if ($type == "reference") {
                        // weblink
                        $a = ($_POST['stay'] == '2') ? "27&id=$id" : "72&pid=$parent";
                    } else {
                        // document
                        $a = ($_POST['stay'] == '2') ? "27&id=$id" : "4&pid=$parent";
                    }
                    $header = "Location: index.php?a=" . $a . "&r=1&stay=" . $_POST['stay'].$add_path;
                } else {
                    $header = "Location: index.php?a=3&id=$id&r=1".$add_path;
                }
            }
            if (headers_sent()) {
                $header = str_replace('Location: ','',$header);
                echo "<script>document.location.href='$header';</script>\n";
            } else {
                header($header);
            }
            break;
        default :
            $modx->webAlertAndQuit("No operation set in request.");
}
