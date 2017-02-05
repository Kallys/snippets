#!/usr/bin/php

<?php

/**
 *  LICENSE: GNU General Public License, version 3 (GPLv3)
 *  Copyright (C) 2017 Kallys
 *  
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *  
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

function ToUTF8($filename, $destination)
{
	if(is_file($filename))
	{
		exec('isutf8 '.escapeshellarg($filename), $output, $result);
		
		if($result === 0)
		{
			copy($filename, $destination);
		}
		else
		{
			exec('iconv -f ISO-8859-15 -t UTF-8 '.escapeshellarg($filename).' -o '.escapeshellarg($destination), $output, $result);
		}
	}
	else
	{
		echo "Unable to convert '$filename': not a valid file.\n";
	}
}

// Argument 1 is path to video file
$path = $argv[1];

if(is_file($path))
{
	echo "=> $path\n";
	$path_info = pathinfo($path);
	
	// Search for subtitles
	foreach(glob($path_info['filename'].'*.srt') as $filename)
	{
		if(substr($filename, 0, -4) == $path_info['filename'])
		{
			echo "\tSubtitle 'fre' found!\n";
			$subtitles['fre'] = $filename;
		}
		else if(preg_match("/\.([a-z]{2,3})\.srt$/i", $filename, $matches))
		{
			echo "\tSubtitle '$matches[1]' found!\n";
			$subtitles[$matches[1]] = $filename;
		}
	}
	
	if(empty($subtitles))
	{
		echo "\tNo subtitle found.\n";
		return 1;
	}
	
	// Prepare them
	$sub_options = array();
	foreach($subtitles as $key=>&$sub)
	{
		ToUTF8($sub, '~'.$sub);
		$sub = '~'.$sub;
		
		// Set french preferred language
		$opt = '--language 0:'.$key.' '.escapeshellarg($sub);
		if($key == 'fre')
		{
			array_unshift($sub_options, $opt);
		}
		else
		{
			array_push($sub_options, $opt);
		}
	}
	
	$sub_options = implode(' ', $sub_options);
	$destination = $path_info['filename'].'.mkv';

	for($i=1; file_exists($destination); $i++)
	{
		$destination = $path_info['filename'].'('.$i.').mkv';
	}
	
	echo "\tMerging...";
	exec('mkvmerge -o '.escapeshellarg($destination).' '.escapeshellarg($path).' '.$sub_options, $output, $result);
	
	if($result == 0)
	{
		echo "\n\tDone!\n";
	}
	else
	{
		echo "\n\tFailed. ($output[1])\n";
	}
	
	// Cleanup
	foreach($subtitles as $sub)
	{
		unlink($sub);
	}
}
else
{
	echo "'$path' is not a valid file!\n";
}

?>
