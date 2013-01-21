<?php

class nzbInfo
{  
	public $source = '';
	public $groups = array();
	public $filecount = 0;
	public $parcount = 0;
	public $rarcount = 0;
	public $zipcount = 0;
	public $videocount = 0;
	public $audiocount = 0;
	public $filesize = 0;
	public $poster	= '';
	public $postedfirst = 0;
	public $postedlast = 0;
	public $completion = 0;
	public $segmenttotal = 0;
	public $segmentactual = 0;
	
	public $nzb = array();
	public $nfofiles = array();
	public $samplefiles = array();
	public $mediafiles = array();
	public $audiofiles = array();
	public $rarfiles = array();
	
	private $isLoaded = false;
	
	function nzbInfo()
	{
		$this->nfofileregex = '/.*(?!read)\.nfo[ "\)\]\-]?/i';
		$this->mediafileregex = '/.*\.(AVI|VOB|MKV|MP4|TS|WMV|MOV|M4V|F4V|MPG|MPEG)(\.001)?[ "\)\]]/i';
		$this->audiofileregex = '/\.(MP3|AAC|OGG)[ "\)\]]/i';
		$this->rarfileregex = '/.*\W(?:part0*1|(?!part\d+)[^.]+)\.(rar|001|1)[ "\)\]\-]/i';
	}
	
	public function loadFromString($str)
	{
		if (empty($this->source))
			$this->source = 'string';
			
		$xmlObj = @simplexml_load_string($str);
		if ($this->isValidNzb($xmlObj))
			$this->parseNzb($xmlObj);
			
		unset($xmlObj);
		
		return $this->isLoaded;
	}
	
	public function loadFromFile($loc)
	{
		$this->source = $loc;
		
		if (file_exists($loc))
		{
			if (preg_match('/\.gz$/i', $loc))
			{
				$data = implode('', gzfile($loc));
				return $this->loadFromString($data);
			}
			
			$xmlObj = @simplexml_load_file($loc);
			if ($this->isValidNzb($xmlObj))
				$this->parseNzb($xmlObj);
			
			unset($xmlObj);
		}
		return $this->isLoaded;
	}
	
	public function summarize()
	{
		$out = array();
		$out[] = 'Reading from '.basename($this->source).'...';
		if (!empty($this->nfofiles))
			$out[] = ' -nfo detected';
		if (!empty($this->samplefiles))
			$out[] = ' -sample detected';
		if (!empty($this->mediafiles))
			$out[] = ' -media detected';
		if (!empty($this->audio))
			$out[] = ' -audio detected';
			
		$out[] = ' -pstr: '.$this->poster;
		$out[] = ' -grps: '.implode(', ', $this->groups);
		$out[] = ' -size: '.round(($this->filesize / 1048576), 2).' MB in '.$this->filecount.' Files';
		$out[] = '       -'.$this->rarcount.' rars';
		$out[] = '       -'.$this->parcount.' pars';
		$out[] = '       -'.$this->zipcount.' zips';
		$out[] = '       -'.$this->videocount.' videos';
		$out[] = '       -'.$this->audiocount.' audios';
		$out[] = ' -cmpltn: '.$this->completion.'% ('.$this->segmentactual.'/'.$this->segmenttotal.')';
		$out[] = ' -pstd: '.date("Y-m-d H:i:s", $this->postedlast);
		$out[] = '';
		$out[] = '';
		
		return implode(PHP_EOL, $out);
	}
	
	private function isValidNzb($xmlObj)
	{
		if (!$xmlObj || strtolower($xmlObj->getName()) != 'nzb') 
			return false;
		else
	    	return true;
	}
	
	private function parseNzb($xmlObj)
	{		
		foreach($xmlObj->file as $file) 
		{	
			$fileArr = array();
			$fileArr['subject'] = (string) $file->attributes()->subject;
			$fileArr['poster'] = (string) $file->attributes()->poster;
			$fileArr['posted'] = (int) $file->attributes()->date;
			$fileArr['groups'] = array();
			$fileArr['filesize'] = 0;
			$fileArr['segmenttotal'] = 0;
			$fileArr['segmentactual'] = 0;
			$fileArr['completion'] = 0;
			$fileArr['segments'] = array();
			
			//subject
			$subject = $fileArr['subject'];
			
			//poster
			$this->poster = $fileArr['poster'];
			
			//dates
			$date = $fileArr['posted'];
			if ($date > $this->postedlast || $this->postedlast == 0)
				$this->postedlast = $date;
			
			if ($date < $this->postedfirst || $this->postedfirst == 0)
				$this->postedfirst = $date;
			
			
			//groups
			foreach ($file->groups->group as $group)
			{
				$this->groups[] = (string) $group;
				$fileArr['groups'][] = (string) $group;
			}
			
			//file segments
			foreach($file->segments->segment as $segment) 
			{
				$bytes = (int) $segment->attributes()->bytes;
				$number = (int) $segment->attributes()->number;
				
				$this->filesize += $bytes;
				$this->segmentactual++;
				
				$fileArr['filesize'] += $bytes;
				$fileArr['segmentactual']++;
				$fileArr['segments'][$number] = (string) $segment;
			}
			
			if (preg_match('/\(?(\d{1,4})\/(?P<total>\d{1,4})\)?$/', $subject, $parts))
			{
				$this->segmenttotal += (int) $parts['total'];
				$fileArr['segmenttotal'] = (int) $parts['total'];
			}
			
			if ($fileArr['segmenttotal'] != 0)
				$fileArr['completion'] = number_format(($fileArr['segmentactual']/$fileArr['segmenttotal'])*100, 0);
			
			//file counts
			$this->filecount++;
			
			if (preg_match($this->nfofileregex, $subject))
				$this->nfofiles[] = $fileArr;
			
			if (preg_match($this->mediafileregex, $subject) && preg_match('/sample[\.\-]/i', $subject) && !preg_match('/\.par2|\.srs/i', $subject))
				$this->samplefiles[] = $fileArr;
		
			if (preg_match($this->mediafileregex, $subject) && !preg_match('/sample[\.\-]/i', $subject) && !preg_match('/\.par2|\.srs/i', $subject))
			{
				$this->mediafiles[] = $fileArr;
				$this->videocount++;
			}
			
			if (preg_match('/\.(rar|r\d{2,3}|1)(?!\.)/i', $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
				$this->rarcount++;
				
			if (preg_match($this->rarfileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
				$this->rarfiles[] = $fileArr;
			
			if (preg_match($this->audiofileregex, $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
			{
				$this->audiofiles[] = $fileArr;
				$this->audiocount++;
			}
			
			if (preg_match('/\.par2(?!\.)/i', $subject))
				$this->parcount++;
			
			if (preg_match('/\.zip(?!\.)/i', $subject) && !preg_match('/\.(par2|vol\d+\+|sfv|nzb)/i', $subject))
				$this->zipcount++;
				
			$this->nzb[] = $fileArr;
		}

		$this->groups = array_unique($this->groups);
		
		if ($this->segmenttotal > 0)
			$this->completion = number_format(($this->segmentactual/$this->segmenttotal)*100, 0);
		
		if (is_array($this->nzb) && !empty($this->nzb))
			$this->isLoaded = true;
			
		return $this->isLoaded;
	}
	
}
