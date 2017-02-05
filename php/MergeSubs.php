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

// See: http://stackoverflow.com/questions/27907913/php-trying-to-get-fgets-to-trigger-both-on-crlf-cr-and-lf
final class EOLStreamFilter extends php_user_filter
{
	public function filter($in, $out, &$consumed, $closing)
	{
		while($bucket = stream_bucket_make_writeable($in))
		{
			$bucket->data = str_replace(["\r\n", "\r"], "\n", $bucket->data);
			$consumed += $bucket->datalen;
			stream_bucket_append($out, $bucket);
		}
		return PSFS_PASS_ON;
	}
}

// Argument 1 is path to video file
$path = $argv[1];

if(is_file($path))
{
	echo "=> $path\n";
	$path_info = pathinfo($path);
	$dir_path = realpath($path_info['dirname']) . '/';
	
	// Search for subtitles
	foreach(glob($dir_path . $path_info['filename'].'*.srt') as $file_path)
	{
		$filename = pathinfo($file_path, PATHINFO_FILENAME);
		
		if($filename == $path_info['filename'])
		{
			echo "\tSubtitle 'fre' found!\n";
			$subtitles['fre'] = $file_path;
		}
		else if(preg_match('/\.([a-z]{3})$/i', $filename, $matches))
		{
			echo "\tSubtitle '$matches[1]' found!\n";
			$subtitles[$matches[1]] = $file_path;
		}
	}
	
	if(empty($subtitles))
	{
		echo "\tNo subtitle found.\n";
		return 1;
	}
	
	// Prepare them
	$sub_options = array();
	foreach($subtitles as $key => $sub)
	{
		ToUTF8($sub, $sub . '~');
		$subtitles[$key] = $sub . '~';
		
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
	$destination = $dir_path . $path_info['filename'].'.mkv';

	for($i=1; file_exists($destination); $i++)
	{
		$destination = $dir_path . $path_info['filename'].'('.$i.').mkv';
	}
	
	echo "\tMerging...";
	
	$command = 'mkvmerge -o '.escapeshellarg($destination).' '.escapeshellarg($path).' '.$sub_options;
	$process = proc_open($command, [
		0 => ['pipe', 'r'], // pipe stdin
		1 => ['pipe', 'w'], // pipe stdout
		2 => ['pipe', 'w']  // pipe stderr
	], $pipes);
	
	if(is_resource($process))
	{
		stream_filter_register("EOL", "EOLStreamFilter");
		stream_filter_append($pipes[1], "EOL");
		$out = '';
	
		// 'Merging...' => 'Merging : 100%'
		echo "\033[3D"; // Move 3 characters backward
		echo ' :     ';
		flush();
	
		while(($o = fgets($pipes[1], 22)) !== false)
		{
			if(preg_match('/:\s*([0-9]+)%/', $o, $matches))
			{
				echo "\033[4D"; // Move 4 characters backward
				echo str_pad($matches[1], 3, ' ', STR_PAD_LEFT) . '%'; // Output is always 5 characters long
				sleep(1); // wait for a while, so we see the animation
				flush();
			}
			else
			{
				$out .= $o;
			}
		}
	
		$errors = stream_get_contents($pipes[2]);
	}
	
	// Close pipes
	foreach($pipes as $pipe)
	{
		fclose($pipe);
	}

	// Cleanup
	foreach($subtitles as $sub)
	{
		unlink($sub);
	}
	
	if(proc_close($process) == 0)
	{
		// 'Merging : 100%' => 'Merging : Done!'
		echo "\033[4D"; // Move 4 characters backward
		echo "Done!\n";
		return 0;
	}
	else
	{
		// 'Merging : 100%' => 'Merging : Done!'
		echo "\033[4D"; // Move 4 characters backward
		echo "Failed!\n\t\t";
		echo $errors ?: preg_replace('/^.+\n/', '', $out);
		return 1;
	}
}
else
{
	echo "'$path' is not a valid file!\n";
	return 1;
}

?>
