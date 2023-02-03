<?php
/*
 * Script for updating all 2.0 model directories
 *
 * Steps:
 *	Read in model-metadata.json template at repo root directory
 *  For each folder in ./2.0/
 *		Open metadata.json file (local-data)
 *			If no file, then create it with initial values from template+
 *		Populate template with local-data
 *		Report any missing mandatory fields
 *		Create (Update) UI files
 *			README1.md (model-specific)
 *			<tag-N>.md (store in data-structure for later creation)
 *			dep5 for licenses
 *		<end-create>
 *	<end-for>
 *
 **/
$VERSION = 'V0.9.9';
print "Start of $VERSION\n";
 
// Global Data
 // Metadata JSON template file
$Templates['Metadata']= ['file'=>'./metadata.template.json', 'type'=>'JSON', 'version'=>2, 'model'=>'metadata.json'];

// README template file
$Templates['Readme'] = ['file'=>'./README.template.md', 'type'=>'MD', 'outputName'=>'README1.md'];

// Conversion arrays
$TF = array ('TRUE'=>true, 'FALSE'=>false, true=>true, false=>false, 1=>true, 0=>false);
$LICENSE = array (
			'CC0'		=> array (
							'icon'=>'https://licensebuttons.net/p/zero/1.0/88x31.png', 
							'link'=>'https://creativecommons.org/publicdomain/zero/1.0/legalcode',
							'text'=>'CC0 1.0 Universal',
							'spdx'=>'CC0',
							),
			'CC-BY'		=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by-nd/4.0/legalcode',
							'text'=>'Attribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			'CC-BY 4.0'	=> array (
							'icon'=>'https://licensebuttons.net/l/by/3.0/88x31.png', 
							'link'=>'https://creativecommons.org/licenses/by-nd/4.0/legalcode',
							'text'=>'Attribution 4.0 International',
							'spdx'=>'CC-BY-4.0',
							),
			);

// Get Existing Model data for pre-populating model JSON files
$ModelData = getModelData();

// Process Model directory
CreateUI ('./2.0', $Templates, $ModelData);
print "\n\nEnd of $VERSION\n";
exit;

// Build repo-wide license file. This file is completely autogenerated
function CreateReuse ($rootPath, $MetadataAll) {
	$contents = array();
	$contents[] = "Format: https://www.debian.org/doc/packaging-manuals/copyright-format/1.0/";
	$contents[] = "Source: glTF V2.0 models from various sources collected into a Repo";
	$contents[] = "Upstream-Name: glTF V2.0 Model Repo";
	$contents[] = "Upstream-Contact: https://GitHub.com/KhronosGroup/glTF-Sample-Models/";
	$contents[] = "Copyright 2017-2023 Khronos Group";
	$contents[] = "License: CC-BY-4";
	$contents[] = "";
	$contents[] = "Files:";
	$contents[] = "*";
	$contents[] = "Copyright 2017-2023 Khronos Group";
	$contents[] = "License: CC-BY-4";
	$contents[] = "";

	for ($ii=0; $ii<count($MetadataAll); $ii++) {
		//$license = getLicenseDep5($MetadataAll[$ii]);
		$contents[] = "Files:";
		$contents[] = $MetadataAll[$ii]->{'pathModel'} . '/*/*';
		for ($jj=0; $jj<count($MetadataAll[$ii]->{'Legal'}); $jj++) {
			$contents[] = "Copyright " . $MetadataAll[$ii]->{'Legal'}[$jj]['year'] . " " . $MetadataAll[$ii]->{'Legal'}[$jj]['owner'];
			$contents[] = "License: " . $MetadataAll[$ii]->{'Legal'}[$jj]['license'];
		}
		$contents[] = '';
	}

	$F = fopen ($rootPath.'.reuse/DEP5.txt', 'w');
	fwrite ($F, join("\n", $contents));
	fclose ($F);
}

// Get all model data, stored in CSV file
function getModelData() {
	$dataFile = 'ModelRepoData.csv';
	$FH = fopen ($dataFile, "r");
	$ModelData = array();
	$keys = fgetcsv($FH, 5000);
	while (($row = fgetcsv($FH, 5000)) !== false) { 
		$new = array();
		for ($ii=0; $ii<count($row); $ii++) {
			$new[$keys[$ii]] = $row[$ii];
		}
		$ModelData[$new['Key']] = $new;
	}
	fclose ($FH);
	return $ModelData;
}



// Function for processing an entire folder of model directories
function CreateUI ($modelFolder, $Templates, $ModelData) {

// Get Metadata template structure
	$Templates['Metadata']['Structure'] = getFileStructure ($Templates['Metadata']);

// Get README template structure
	$Templates['Readme']['Structure'] = getFileStructure ($Templates['Readme']);

/*
 * Create output structure
 *	Each element describes a single model and contains
 *		Path to model directory
 *		Name of model
 *		Array of applicable tags
 *		License of entire model (use <multiple> to indicate incompatible licenses)
 *		Array of licenses where each element identifies a file and its associated license(s)
 *		Copyright year
 *		Copyright owner (string)
 **/
	$Models = [];

// Loop through all matching directories
	$folder = dir ($modelFolder);
	$folderDotDirs = array ($modelFolder.'/.', $modelFolder.'/..');

	$F = fopen ('modelMetadata.csv', 'w');
	while (false !== ($model = $folder->read())) {
		$modelDir = $folder->path . '/' . $model;
		$metaFilename = $Templates['Metadata']['model'];
		if (is_dir($modelDir) && !($model == '.' || $model == '..')) {
			$metadata = getMetadata ($modelDir, $metaFilename, ['name'=>$model], $Templates['Metadata'], $ModelData);
/*
			fwrite ($F, sprintf ('%s,%s,%s,%s,%s,%04d,"%s"'."\n", 
							$metadata->{'key'}, 
							$metadata->{'name'}, 
							$metadata->{'path'}, 
							$metadata->{'author'}, 
							$metadata->{'owner'}, 
							$metadata->{'year'}, 
							((is_array($metadata->{'license'})) ? join(' ', $metadata->{'license'}) : $metadata->{'license'}) 
							));
*/
			$metaAll[] = $metadata;
		}
	}
	fclose ($F);
	$folder->close();
	
	print "\n";
	createReadme ('Detailed', 'README-detailed.md', $metaAll);
	createReadme ('Image', 'README-image.md', $metaAll);
	createReadme ('List', 'README-all.md', $metaAll);
	createReadme ('List', 'README-issues.md', $metaAll, array('issues'));
	createReadme ('List', 'README-sharable.md', $metaAll, array('sharable'));
	createReadme ('List', 'README-noLicense.md', $metaAll, array('no-license'));
	createReadme ('List', 'README-noAuthor.md', $metaAll, array('no-author'));
	createReadme ('List', 'README-noOwner.md', $metaAll, array('no-owner'));
	createReadme ('List', 'README-noYear.md', $metaAll, array('no-year'));

	CreateReuse ('./', $metaAll);
}

// Function for creating READMEs
function createReadme ($type, $fname, $metaAll, $tags=array('')) {
	$F = fopen ($fname, 'w');
	$section = 'Tagged...';
	if (count($tags) == 0 || $tags[0] == '') {
		$section = 'All models';
		$singleTag = '';
	} else {
		$section = 'Models tagged with **' . join(', ', $tags) . '**';
		$singleTag = $tags[0];
	}
	print "Generating $type for $section\n";
	
	fwrite ($F, "# glTF 2.0 Sample Models\n\n");
	fwrite ($F, "## $section\n\n");
	
	if ($type == 'Image') {
		$fmtString = "[![%s](%s)](%s)\n";
		for ($ii=0; $ii<count($metaAll); $ii++) {
			fwrite ($F, sprintf ($fmtString, 
						$metaAll[$ii]->{'name'}, 
						$metaAll[$ii]->{'UriHeight'},
						$metaAll[$ii]->{'UriReadme'}
						));
		}

	} else if ($type == 'Detailed') {
		fwrite ($F, "| Model   | Screenshot  | Legal | Description |\n");
		fwrite ($F, "|---------|-------------|-------|-------------|\n");
		$fmtString = "| [%s](%s) | ![](%s) | %s | %s |\n";

		for ($ii=0; $ii<count($metaAll); $ii++) {
/*
			$license = ((is_array($metaAll[$ii]->{'license'})) ? 
							join(', ', $metaAll[$ii]->{'license'}) : $metaAll[$ii]->{'license'});
			$license = ($license == '') ? '**NO LICENSE**' : $license;
			$author  = ((is_array($metaAll[$ii]->{'author'})) ? 
							join('<br>', $metaAll[$ii]->{'author'}) : $metaAll[$ii]->{'author'});
			$author  = str_replace ("\n", '<br>', $author);
			$author  = ($metaAll[$ii]->{'author'}  == '') ? '**NO AUTHOR**' : $author;
			$owner   = ((is_array($metaAll[$ii]->{'owner'})) ? 
							join(', ', $metaAll[$ii]->{'owner'}) : $metaAll[$ii]->{'owner'});
			$owner   = str_replace ("\n", ', ', $owner);
			$owner   = ($metaAll[$ii]->{'owner'}   == '') ? '**NO OWNER**' : $owner;
			
			$notice = array();
			$notice[] = $license;
			if (!($license == 'PD' || $license == 'CC0')) {
				$notice[] = "&copy; " . $metaAll[$ii]->{'year'} . ", $owner.";;
			}
			$notice[] = $author;
*/
			$summary = ($metaAll[$ii]->{'summary'} == '') ? '**NO DESCRIPTION**' : $metaAll[$ii]->{'summary'};

			fwrite ($F, sprintf ($fmtString, 
						$metaAll[$ii]->{'name'}, 
						$metaAll[$ii]->{'UriReadme'},
						$metaAll[$ii]->{'UriShot'},
						join("<br>", $metaAll[$ii]->{'credit'}),
						$summary,
						));
		}
	} else if ($type == 'List') {
		fwrite ($F, "| Model   | Screenshot  | Description |\n");
		fwrite ($F, "|---------|-------------|-------------|\n");
		$fmtString = "| [%s](%s) | ![](%s) | %s<br>Credit:<br>%s |\n";

		for ($ii=0; $ii<count($metaAll); $ii++) {
			if ($singleTag == '' || in_array($singleTag, $metaAll[$ii]->{'tags'})) {
/*
				$license = ((is_array($metaAll[$ii]->{'license'})) ? 
								join(' ', $metaAll[$ii]->{'license'}) : $metaAll[$ii]->{'license'});
				$license = ($license == '') ? '**NO LICENSE**' : $license;
*/
				$summary = ($metaAll[$ii]->{'summary'} == '') ? '**NO DESCRIPTION**' : $metaAll[$ii]->{'summary'};

				fwrite ($F, sprintf ($fmtString, 
							$metaAll[$ii]->{'name'}, 
							$metaAll[$ii]->{'UriReadme'},
							$metaAll[$ii]->{'UriShot'},
							$summary,
							join("<br>", $metaAll[$ii]->{'credit'}),
							));
			}
		}
	}
	fclose ($F);
	return;
}

// Function to return the model's metadata
// This may need to create the file
function getMetadata ($modelDir, $metaFilename, $Defaults, $Metadata, $ModelData) {
	$filename = $modelDir . '/' . $metaFilename;
	if (file_exists($filename)) {
		$string = file_get_contents ($filename);
		$metadata = json_decode ($string);
		$needsWriting = false;

	} else {
		$metadata = $Metadata['Structure'];
		$needsWriting = true;
	}

	if (!isset($metadata->{'version'}) || $metadata->{'version'} < $Metadata['version']) {
		$metadata = updateMetadata ($metadata, $modelDir, $Defaults, $Metadata['Structure'], $ModelData);
		$needsWriting = true;
	}
	$metadata = cleanupMetadata ($metadata, $Defaults['name'], $modelDir);
	
	if ($needsWriting) {
		$modelMeta = exportToTemplate ($metadata, $Metadata['Structure']);
		$string = json_encode($modelMeta, JSON_PRETTY_PRINT);
		$FH = fopen ($filename, "w");
		fwrite ($FH, $string);
		fclose ($FH);
	}
	createModelReadme ($metadata);
	createModelLicense ($metadata);

	return $metadata;
}

/*
 * Returns the template-based metadata for output to the repo
 *
**/
function exportToTemplate ($model, $template) {
	$output = clone ($template);
	foreach ($output as $key => $value) {
		$output->{$key} = $model->{$key};
	}
	return $output;
}


/*
 * Clean up the model's metadata so that it is suitable for creating a README. 
 * Populates fields for direct output to the README. These fields can
 * be created each processing pass, so there is no reason to save them
 * The fields are array elements of ['Credit'] where each element is a credit line of the form:
 *		(c) <year>, <owner>. <License>
 *		 - <artist> for <description>
 *
 * The actual work is determined by the field $metadata->{'AutoGenerateREADME'}
 * Uses data from the Summary, License, Author, Owner, Year
 */
function cleanupMetadata ($metadata, $modelKey, $dir) {
	global $TF, $LICENSE;

	if ($metadata->{'version'} < 2) {
		$artist  = ($metadata->{'author'} == '') ? '_No Artist_' : $metadata->{'author'};
		$owner  = ($metadata->{'owner'} == '') ? '_No Owner_' : $metadata->{'owner'};
		$license = ($metadata->{'license'} == '') ? '_No License_' : $metadata->{'licenseText'};
		$year = ($metadata->{'year'} < 100) ? 0 : $metadata->{'year'};
		$legal[0] = array(
						'year'		=> $year,
						'owner'		=> $owner,
						'license'	=> $license,
						'artist'	=> $artist,
						'what'		=> ''
						);
	} else {
		for ($ii=0; $ii<count($metadata->{'Legal'}); $ii++) {
			/*
			$artist  = ($metadata->{'author'} == '') ? '_No Artist_' : $metadata->{'author'};
			$owner  = ($metadata->{'owner'} == '') ? '_No Owner_' : $metadata->{'owner'};
			$license = ($metadata->{'license'} == '') ? '_No License_' : $metadata->{'licenseText'};
			$year = ($metadata->{'year'} < 100) ? 0 : $metadata->{'year'};
			*/
			$legal[] = array(
						'year'		=> $metadata->{'Legal'}[$ii]->{'year'},
						'owner'		=> $metadata->{'Legal'}[$ii]->{'owner'},
						'license'	=> $metadata->{'Legal'}[$ii]->{'license'},
						'artist'	=> $metadata->{'Legal'}[$ii]->{'artist'},
						'what'		=> $metadata->{'Legal'}[$ii]->{'what'}
						);
		}
	}

	$summary = ($metadata->{'summary'} == '') ? '_No Summary_' : $metadata->{'summary'};
	/*
	if ($metadata->{'license'} == 'PD' || $metadata->{'license'} == 'CC0') {
		$credit = ($metadata->{'licenseDetails'}) ? 
			sprintf ("**License:** [![%s](%s) %s](%s)", $license, $metadata->{'licenseIcon'}, $license, $metadata->{'licenseLink'})
			:
			$credit = 'None required';
	} else {
		// Format: (c) <year>, <owner>. <license>
		$copyright = ($metadata->{'year'} > 100) ? sprintf ("**&copy;** %4d,", $metadata->{'year'}) : "**&copy;**";
		$credit = ($metadata->{'licenseDetails'}) ? 
			sprintf ("%s %s. **License:** [![%s](%s) %s](%s)", $copyright, $owner, $license, $metadata->{'licenseIcon'}, $license, $metadata->{'licenseLink'})
			:
			sprintf ("%s %s. **License:** %s", $copyright, $owner, $license);
	}
	*/
	for ($ii=0; $ii<count($legal); $ii++) {
		$credit[] = sprintf ("&copy; %04d, %s. %s", $legal[$ii]['year'], $legal[$ii]['owner'], $legal[$ii]['license']);
		$credit[] = sprintf (" - %s for %s", $legal[$ii]['year'], $legal[$ii]['artist'], $legal[$ii]['what']);
	}

	$metadata->{'summary'}		= $summary;
	//$metadata->{'artist'}		= $artist;
	//$metadata->{'owner'}		= $owner;
	//$metadata->{'licenseText'}	= $license;
	$metadata->{'Legal'}		= $legal;
	$metadata->{'license'}		= $legal[0]['license'];
	$metadata->{'credit'}		= $credit;
	
	$screenShot = ($metadata->{'screenshot'} == '') ? 'screenshot/screenshot.jpg' : $metadata->{'screenshot'};
	$screenComponents = explode ('.', $screenShot);
	$screenFile = $screenComponents[0];
	$metadata->{'screenshotType'} = (file_exists($dir.'/'.$screenFile.'.jpg')) ? 'jpg' : ((file_exists($dir.'/'.$screenFile.'.png')) ? 'png' : 'gif');

// Create standard-height image
	if ($metadata->{'screenshotType'} == 'jpg' || $metadata->{'screenshotType'} == 'png') {
		$shotHeight = createScreenShot ($dir, $screenFile, $metadata->{'screenshotType'}, 150);
	} else {
		$shotHeight = $screenFile . '.' . $metadata->{'screenshotType'};
	}

	$metadata->{'key'} = $modelKey;
	$metadata->{'path'} = 'https://github.com/KhronosGroup/glTF-Sample-Models/tree/master/' . $dir;
	$metadata->{'screenshot'} = $screenFile . '.' . $metadata->{'screenshotType'};
	$metadata->{'pathModel'} = $dir;
	$metadata->{'pathShot'} = $dir . '/' . $metadata->{'screenshot'};
	$metadata->{'UriShot'} = rawurlencode($metadata->{'pathShot'});
	$metadata->{'shotHeight'} = $shotHeight . '.' . $metadata->{'screenshotType'};
	$metadata->{'pathHeight'} = $dir . '/' . $metadata->{'shotHeight'};
	$metadata->{'pathReadme'} = $dir . '/README.md';
	$metadata->{'pathLicense'} = $dir . '/LICENSE.md';
	$metadata->{'UriHeight'} = rawurlencode($metadata->{'pathHeight'});
	$metadata->{'UriReadme'} = rawurlencode($dir . '/README.md');

	return $metadata;
}

/*
 * Create the model's LICENSE markdown file.
 * This is always created based on the information in $metadata
 * Uses data from the Summary, License, Author, Owner, Year
 */
function createModelLicense ($metadata) {
	global $TF, $LICENSE, $VERSION;

	$readme = array();
	$readme[] = '# LICENSE file for the model: ' . $metadata->{'name'};
	$readme[] = 'All files in this directory tree are licensed as indicated below.';
	$readme[] = '* All files directly associated with the model including all text, image and binary files:';
	if (isset($LICENSE[$metadata->{'license'}])) {
		$readme[] = '  * [' . $LICENSE[$metadata->{'license'}]['text'] . ']("' . $LICENSE[$metadata->{'license'}]['link'] . '") [SPDX license identifier: "' . $LICENSE[$metadata->{'license'}]['spdx'] . '"]';
	} else {
		$readme[] = '  * **Unknown license:** ' . $metadata->{'license'};
	}
	$readme[] = '* This file and all other metadocumentation files including "metadata.json":';
	$readme[] = '  * [Creative Commons Attribtution 4.0 International]("'.$LICENSE['CC-BY 4.0']['link'].'") [SPDX license identifier: "CC-BY-4.0"]';
	$readme[] = 'Full license text of these licenses are available at the links above';
	$readme[] = "#### Generated by ui-generator.php $VERSION";
	$output = join ("\n\n", $readme);

	$FO = fopen ($metadata->{'pathLicense'}, 'w');
	fwrite ($FO, $output);
	fclose ($FO);

	return;
}


/*
 * Create the model's readme.
 * The actual work is determined by the field $metadata->{'AutoGenerateREADME'}
 * Uses data from the Summary, License, Author, Owner, Year
 */
function createModelReadme ($metadata) {
	global $TF, $LICENSE, $VERSION;

	if (!$metadata->{'AutoGenerateREADME'}) {
		return;
	}

	$screenshot = $metadata->{'screenshot'};

	$readme = array();
	$readme[] = '# ' . $metadata->{'name'};
	$readme[] = "## Summary";
	$readme[] = $metadata->{'summary'};
	$readme[] = '## Screenshot';
	$readme[] = "![screenshot](".$metadata->{'screenshot'}.")";
	$readme[] = '## Legal';
	for ($ii=0; $ii<count($metadata->{'credit'}); $ii++) {
		$readme[] = $metadata->{'credit'}[$ii];
	}
	//$readme[] = $metadata->{'credit'} . '<br>**Artist:** ' . $metadata->{'artist'};
	$readme[] = "#### Generated by ui-generator.php $VERSION";
	$output = join ("\n\n", $readme);

	$FO = fopen ($metadata->{'pathReadme'}, 'w');
	fwrite ($FO, $output);
	fclose ($FO);

	return;
}

/* 
 * Function to update Metadata structure 
 * Supports all versions
 * Initial release supports upgrade from 0 to 1
 *	0 is basic created from runtime
 *	1 is standard release with all available information
 *
 *	V0 needs information loaded from existing README
 **/
function updateMetadata ($metadata, $dir, $Defaults, $Structure, $ModelData) {
	global $TF, $LICENSE;
	if (!isset($metadata->{'version'}) || $metadata->{'version'} == 0) {
		//print "Updating metadata with info from $dir/README.md and |".$metadata->{'name'}."|\n";
		
		$string = file_get_contents ($dir . '/README.md');
		$readme = explode (PHP_EOL, $string);
		if (count($readme) == 1) {$readme = explode ("\n", $string); }
		$modelName = $metadata->{'name'};
		$metadata = clone $Structure;
		$license = (isset($metadata->{'license'})) ? $metadata->{'license'} : [];
		$description = (isset($metadata->{'description'})) ? $metadata->{'description'} : [];
		
		if (substr($readme[0], 0, 2) == '# ') {
			$modelName = substr($readme[0], 2);
			//print " ... Updating name\n";
		}
		
// Description may or may not be in the file. It starts at '## Description' and runs until 
//	the end of file or another section starting with '## '
		$shortDescription = '... no description ...';
		for ($ii=0; $ii<count($readme)-1; $ii++) {
			if ($readme[$ii] == '## Description') {
				$shortDescription = '... nothing ...';
				$description = array();
				for ($jj=$ii+1; $jj<count($readme); $jj++) {
					if (substr($readme[$jj], 0, 3) == '## ') {
						break;
					} else if ($readme[$jj] != '') {
						$description[] = $readme[$jj];
					}
				}
				if (count($description) != 0) {
					$shortDescription = $description[0];
				}
				//print " ... Updating description\n";
			}
		
// License is last section in the file ** ASSUMPTION **
			//print "[$ii] |".$readme[$ii]."|\n";
			if ($readme[$ii] == '## License Information') {
				for ($jj=$ii+1; $jj<count($readme); $jj++) {
					if (rtrim($readme[$jj]) != '') {
						$license[] = $readme[$jj];
					}
				}
			}
		}

// Update all fields from V1
		if (isset($ModelData[$modelName])) {
			$metadata->{'license'} = $ModelData[$modelName]['License'];
			$metadata->{'summary'} = $ModelData[$modelName]['Summary'];
			$metadata->{'author'} = $ModelData[$modelName]['Author'];
			$metadata->{'owner'} = $ModelData[$modelName]['Owner'];
			$metadata->{'year'} = $ModelData[$modelName]['Year'];
			$metadata->{'AutoGenerateREADME'} = $TF[$ModelData[$modelName]['AutoGenerateREADME']];
			//print "  AutoGenerate '$modelName': |".$ModelData[$modelName]['AutoGenerateREADME']."|\n";
		} else {
			$metadata->{'license'} = join (' AND ', $license);
			$metadata->{'summary'} = $shortDescription;
			$metadata->{'author'} = '';
			$metadata->{'owner'} = '';
			$metadata->{'year'} = 0;
			$metadata->{'AutoGenerateREADME'} = false;
			//print "  -- No model data for '$modelName' -- Check for characters past end\n";
			//print "  -- |".rawurlencode($modelName)."|\n";
		}
		$metadata->{'year'} = ($metadata->{'year'} == '') ? 0 : $metadata->{'year'};

	}

	foreach ($Structure as $key => $value) {
		if (!isset($metadata->{$key})) {$metadata->{$key} = $value;}
	}

	$screenshot = 'screenshot/screenshot';
	
	$metadata->{'version'} = 0;
	$metadata->{'name'} = $modelName;
	$metadata->{'author'} = $metadata->{'author'};
	$metadata->{'owner'} = $metadata->{'owner'};
	$metadata->{'year'} = $metadata->{'year'};
	$metadata->{'description'} = $description;

// These aren't really needed. See Cleanup routine for details.
	$metadata->{'licenseDetails'} = (isset($LICENSE[$metadata->{'license'}])) ? true : false;
	$metadata->{'licenseIcon'} = ($metadata->{'licenseDetails'}) ? $LICENSE[$metadata->{'license'}]['icon'] : '';
	$metadata->{'licenseLink'} = ($metadata->{'licenseDetails'}) ? $LICENSE[$metadata->{'license'}]['link'] : '';
	$metadata->{'licenseText'} = ($metadata->{'licenseDetails'}) ? $LICENSE[$metadata->{'license'}]['text'] : $metadata->{'license'};

	$metadata->{'Legal'}[0] = array (
								"author"	=> $metadata->{'author'},
								"owner"		=> $metadata->{'owner'},
								"year"		=> $metadata->{'year'},
								"license"	=> $license,
								"what"		=> "tbd",
							);

	$tags = array();
	$tags = getTags ($metadata);
	$metadata->{'tags'} = $tags;

	return $metadata;
}

// Return the tags appropriate to this model
function getTags ($metadata) {
	$issues = false;
	$license = (is_array($metadata->{'license'})) ? 
					trim(join('<br>', $metadata->{'license'})) : 
					trim($metadata->{'license'});

	if ($license == '') {
		$tags[] = 'no-license';
		$issues = true;
	} else {
		$tags[] = 'sharable';
	}
	if ($metadata->{'summary'} == '') {
		$issues = true;
	}
	if (trim($metadata->{'author'}) == '') {
		$tags[] = 'no-author';
		$issues = true;
	}
	if ($license != 'CC0' && trim($metadata->{'owner'}) == '') {
		$tags[] = 'no-owner';
		$issues = true;
	}
	if ($license != 'CC0' && trim($metadata->{'year'}) == 0) {
		$tags[] = 'no-year';
		$issues = true;
	}

	if ($issues) {
		$tags[] = 'issues';
	}
	return $tags;
}

// Function to create standard size screenshots
function createScreenShot ($path, $shotOriginal, $shotType, $imageHeight) {
	$shotOut = sprintf ('%s-x%d', $shotOriginal, $imageHeight);
	if (!file_exists("$path/$shotOut.$shotType")) {
		$cmd = sprintf ('magick "%s/%s.%s" -background white -resize %d "%s/%s.%s"',
							$path, $shotOriginal, $shotType,
							$imageHeight,
							$path, $shotOut, $shotType);
		system ($cmd);
	}
	return $shotOut;
}

// Function to read and parse the specified file based on a structure
// File I/O errors are fatal
function getFileStructure ($templateStructure) {
	if (! file_exists($templateStructure['file'])) {
		print "Unable to find template: " . $templateStructure['file'] . "\nAborting\n";
		die (2);
	}
	$string = file_get_contents ($templateStructure['file']);
	//print "Template file ($".$templateStructure['file']."): $string\n";
	
	// Parse the file contents based on the type 
	if ($templateStructure['type'] == 'JSON') {
		$retval = json_decode ($string);
	} else if ($templateStructure['type'] == 'MD') {
		$retval = explode (PHP_EOL, $string);
	} else {
		$retval = $string;
	}
	
	return $retval;
}