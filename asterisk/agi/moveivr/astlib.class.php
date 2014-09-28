<?php

ob_implicit_flush(true);

class AGI {
	private $s_in;
	private $s_out;
	private $ascii_map = array(
		32 => ' ',  33 => '!',  34 => '"',  35 => '#',  36 => '$',  37 => '%',  38 => '&',  39 => "'",
		40 => '(',  41 => ')',  42 => '*',  43 => '+',  44 => ',',  45 => '-',  46 => '.',  47 => '/',
		48 => '0',  49 => '1',  50 => '2',  51 => '3',  52 => '4',  53 => '5',  54 => '6',  55 => '7',
		56 => '8',  57 => '9',  58 => ':',  59 => ';',  60 => '<',  61 => '=',  62 => '>',  63 => '?',
		64 => '@',  65 => 'A',  66 => 'B',  67 => 'C',  68 => 'D',  69 => 'E',  70 => 'F',  71 => 'G',
		72 => 'H',  73 => 'I',  74 => 'J',  75 => 'K',  76 => 'L',  77 => 'M',  78 => 'N',  79 => 'O',
		80 => 'P',  81 => 'Q',  82 => 'R',  83 => 'S',  84 => 'T',  85 => 'U',  86 => 'V',  87 => 'W',
		88 => 'X',  89 => 'Y',  90 => 'Z',  91 => '[',  92 => '\\', 93 => ']',  94 => '^',  95 => '_',
		96 => '`',  97 => 'a',  98 => 'b',  99 => 'c', 100 => 'd', 101 => 'e', 102 => 'f', 103 => 'g',
		104 => 'h', 105 => 'i', 106 => 'j', 107 => 'k', 108 => 'l', 109 => 'm', 110 => 'n', 111 => 'o',
		112 => 'p', 113 => 'q', 114 => 'r', 115 => 's', 116 => 't', 117 => 'u', 118 => 'v', 119 => 'w',
		120 => 'x', 121 => 'y', 122 => 'z', 123 => '{', 124 => '|', 125 => '}', 126 => '~',   0 => NULL
	);
	
  
	public $config;
  
	function __construct() {
		$this->s_in = fopen("php://stdin", "r");
		$this->s_out = STDOUT;
		while (1) {
			$in = trim(fgets($this->s_in));
     	 	if ($in == "") {
				break;
			}
			list($key,$val) = split(":", $in, 2);
			$this->config[$key] = trim($val);
    	}
	}

	function __destruct() {
		fclose($this->s_in);
	}

//	private function cmd ($text) {
	public function cmd($text)	{
		fputs($this->s_out, $text);
	}

	private function ret_char () {
		// this needs to be improved to take into account
		// when a value is present
		list($junk,$zz) = split('=', trim(fgets($this->s_in)), 2);
		return $this->ascii_map[$zz];
	}

	private function ret_val () {
		list($code, $res, $val) = split(" ", trim(fgets($this->s_in)), 3);
		return(trim($val, '()'));
	}

	private function ret_code () {
		// this needs to be improved to take into account
		// when a value is present
		list($junk,$zz) = split('=', trim(fgets($this->s_in)), 2);
		return($zz);
	}

	public function say_digits($digits, $escape = FALSE) {
		if (! $escape) {
			$escape = "";
		}
		$this->cmd("SAY DIGITS $digits \"$escape\"\n");
		return $this->ret_char();
	}
	
	public function stream_file($file,$escape = NULL, $offset = NULL) {
		$cmd = "STREAM FILE \"$file\" \"$escape\"";
		if ($offset != NULL) {
			$cmd .= " $offset";
		}
		$this->cmd("$cmd\n");
		return $this->ret_char();
	}

	public function record_file ($filename, $format, $escape, $timeout, $beep = FALSE) {
		if ($beep) {
			$beep = " BEEP ";
		} else {
			$beep = "";
		}
		$this->cmd("RECORD FILE \"$filename\" \"$format\" \"$escape\" $timeout" . $beep . "\n");
		return $this->ret_char();
	}

	public function wait_for_digit ($timeout = 500) {
		$this->cmd("WAIT FOR DIGIT $timeout\n");
		$k = $this->ret_char();
		return $k;
	}
	
	public function get_digit ($timeout = 500) {
		return $this->wait_for_digit($timeout);
	}

	public function verbose ($string) {
		$this->cmd("VERBOSE \"$string\"\n");
		return $this->ret_char();
	}

	public function db_get ($fam, $key) {
		$this->cmd("DATABASE GET \"$fam\" \"$key\"\n");
		return $this->ret_val();
	}

	public function get_data ($file, $maxdigits="", $timeout="") {
		$this->cmd("GET DATA \"$file\" \"$timeout\" \"$maxdigits\"\n");
		return $this->ret_code();
	}

	public function hangup ($channel="") {
		$this->cmd("HANGUP $channel\n");
		return $this->ret_code();
	}
	
	public function dial()	{
		$this->cmd("DIAL \n");
	}

	public function answer () {
		$this->cmd("ANSWER\n");
		return $this->ret_code();
	}

	public function send_text ($text) {
		$this->cmd("SEND TEXT \"$text\"\n");
		return $this->ret_code();
	}

	public function receive_char ($timeout) {
		if (is_int($timeout)) {
			$this->cmd("RECEIVE CHAR $timeout\n");
			return $this->ret_char();
		} else {
			return(FALSE);
		}
	}

	public function tdd_mode ($switch) {
		$switch = strtolower($switch);
		switch ($switch) {
			case "1":
			case "+":
			case "t":
			case "true":
			case "on":
				$switch = "on";
				break;
				
			case "0":
			case "-":
			case "f":
			case "false":
			case "nil":
			case "off":
				$switch = "off";
				
			break;
				default:
				return FALSE;
		}
		
		$this->cmd("TDD MODE $switch\n");
		return $this->ret_code();
	}

	public function send_image ($image) {
		$this->cmd("SEND IMAGE \"$image\"\n");
		return $this->ret_code();
	}

	public function say_number($num,$digits) {
		$this->cmd("SAY NUMBER $num \"$digits\"\n");
		return $this->ret_char();
	}

	public function say_phonetic($str, $digits) {
		$this->cmd("SAY PHONETIC \"$str\" \"$digits\"\n");
		return $this->ret_char();
	}

	public function say_time($time, $digits) {
		$this->cmd("SAY TIME $time \"$digits\"\n");
		return $this->ret_char();
	}

	// Ha Truong for say date
	public function say_date($date, $digits) {
		$this->cmd("SAY DATE $date \"$digits\"\n");
		return $this->ret_char();
	}

	public function set_context($cxt) {
		$this->cmd("SET CONTEXT \"$cxt\"\n");
		return $this->ret_code();
	}

	public function set_extension($ext) {
		$this->cmd("SET EXTENSION \"$ext\"\n");
		return $this->ret_code();
	}

	public function set_priority($pri) {
		if ( is_int($pri)) {
			$this->cmd("SET PRIORITY $pri\n");
			return $this->ret_code();
		} else {
			return FALSE;
		}
	}

	public function set_return_point($context,$extension,$priority) {
		$this->set_context($context);
		$this->set_extension($extension);
		$this->set_priority($priority);
	}

	public function set_autohangup($time) {
		if (is_int($time)) {
			$this->cmd("SET AUTOHANGUP $time\n");
			return $this->ret_code();
		} else {
			return(FALSE);
		}
	}
	
	public function exec ($app, $args="") {
		$this->cmd("EXEC $app \"$args\"\n");
		return $this->ret_code();
	}

	public function set_callerid($number) {
		$this->cmd("SET CALLERID \"$number\"\n");
		return $this->ret_code();
	}

	public function channel_status($channel=NULL) {
		if ($channel) {
			$channel = " \"$channel\"";
		}
		
		$this->cmd("CHANNEL STATUS" . $channel . "\n");
		return $this->ret_code();
	}

	public function set_variable($varname, $value) {
		$this->cmd("SET VARIABLE \"$varname\" \"$value\"\n");
		return $this->ret_val();
	}
	
	public function get_variable($varname) {
		$this->cmd("GET VARIABLE \"$varname\"\n");
		return $this->ret_val();
	}

	public function set_music_on($switch,$class=NULL) {
		if ($class) {
		$class = " \"$class\"";
		}
		
		$switch = strtolower($switch);
		switch ($switch) {
			case "1":
			case "+":
			case "t":
			case "true":
			case "on":
				$switch = "on";
				break;
				
			case "0":
			case "-":
			case "f":
			case "false":
			case "nil":
			case "off":
				$switch = "off";
				break;
				
			default:
				return FALSE;
		}

		$this->cmd("SET MUSIC ON $switch" . $class . "\n");
		return $this->ret_code();
	}
	
	public function noop () {
		$this->cmd("NOOP\n");
		return $this->ret_code();
	}
}

?>
