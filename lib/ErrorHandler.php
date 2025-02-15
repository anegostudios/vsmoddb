<?php
declare(ticks = 100);

/* Log levels */
define("SEVERITY_DEBUG", 1);
define("SEVERITY_INFO", 2);
define("SEVERITY_WARN", 3);
define("SEVERITY_ASSERT", 4);
define("SEVERITY_ERROR", 5);
define("SEVERITY_FATAL", 6);


class ErrorHandler {
	// How many errors have been thrown so far?
	static $errorsthrown = 0;
	// How many timeouts have been triggered so far?
	static $timeoutstriggered = 0;
	// Make sure to print a user error only once
	static $usererrorprinted = false;
	// equivalent to error_reporting()
	static $errorreporting = E_ALL;
	// when in productionmode, in what errors shall we display a user friendly error message? (set in setupErrorHandling())
	static $productionreporting = null;
	// If set, empty files with ip-adresses as names are made where a timeout has happened
	static $timeoutpath = null;
	static $timeoutmessage = null;

	static $remainingexecutiontime = 0;

	static $errorcodes = array(
		E_ERROR => 'E_ERROR',
		E_WARNING => 'E_WARNING',
		E_PARSE => 'E_PARSE',
		E_NOTICE => 'E_NOTICE',
		E_CORE_ERROR => 'E_CORE_ERROR',
		E_CORE_WARNING => 'E_CORE_WARNING',
		E_COMPILE_ERROR => 'E_COMPILE_ERROR',
		E_COMPILE_WARNING => 'E_COMPILE_WARNING',
		E_USER_ERROR => 'E_USER_ERROR',
		E_USER_WARNING => 'E_USER_WARNING',
		E_USER_NOTICE => 'E_USER_NOTICE',
		E_STRICT=> 'E_STRICT' ,
		E_RECOVERABLE_ERROR=> 'E_RECOVERABLE_ERROR',
		E_DEPRECATED => "E_DEPRECATED"
	);


	/* timeouthandling: If set, the ErrorHandler will also handle timeouts.
		Example:
		$timeouthandling = array(
			'remainingexecutiontime' => 10,	   // timeout in seconds until a user times out
			'timeouttrigger' => 5,	   // timeout in seconds until the server is considered overloaded
			'timeoutpath' => "temp/timeouts"   // optional
			'timeoutmessage' => "A timeout happend<br>Please try again later"
		);
	*/
	public static function setupErrorHandling($timeouthandling = null) {
		error_reporting(E_ALL & ~E_DEPRECATED);

		// The types of PHP Errors which should show a user friendly error message when outside the debug mode
		// We hope that only serious errors will disrupt user experience
		self::$productionreporting = E_ERROR | E_PARSE | E_COMPILE_ERROR;

		error_reporting(self::$productionreporting);


		/* Error handling through functions of this class */
		ini_set('display_errors', 0);
		set_exception_handler(
			array("ErrorHandler", "HandleException")
		);
		set_error_handler(
			array("ErrorHandler", "HandleError")
		);
		register_shutdown_function(
			array("ErrorHandler", "HandleShutDown")
		);


		/* Assertion handling:  */
		assert_options(ASSERT_ACTIVE, 1); // Enable assert evaluation
		assert_options(ASSERT_WARNING, 0); // Don't issue php warnings
		assert_options(ASSERT_CALLBACK, array("ErrorHandler", "HandleAssertFailure")); // Log failing asserts


		/* Timeout handling: Call HandleSignal function at process timeout */
		if ($timeouthandling != null) {
			pcntl_signal(SIGALRM, array("ErrorHandler", "HandleSignal"));
			set_time_limit($timeouthandling["maxexecutiontime"] * 2); // Prevent timeout being handled by PHP. Instead we exit on SIGALRM
			pcntl_alarm($timeouthandling["timeouttrigger"]);

			if (!empty($timeouthandling["timeoutpath"])) {
				ErrorHandler::$timeoutpath = $timeouthandling["timeoutpath"];
			}
			ErrorHandler::$remainingexecutiontime = $timeouthandling["maxexecutiontime"] - $timeouthandling["timeouttrigger"];
			ErrorHandler::$timeoutmessage = $timeouthandling["timeoutmessage"];
		}



	}


	public static function setErrorReporting($value) {
		ErrorHandler::$errorreporting = $value;
	}

	public static function isDebugMode() {
		return DEBUG;
	}


	/* User friendly error message */
	public static function printUserError($errno, $e, $errstr) {
		if ((self::$productionreporting & $errno) && !self::$usererrorprinted) {
			if (ob_get_level() > 0) {
				ob_end_flush();
			}
			
			$code = $e->getCode();
			if (isset(self::$errorcodes[$code])) {
				$codename = self::$errorcodes[$code];
			} else {
				$codename = $code;
			}
			

			?>
				<div style="text-align: left; background-color: #fcc; border: 1px solid #600; color: #600; display: block; margin: 1em 0; padding: .33em 6px">
				<b>An Error has occured, please contact Tyron on discord or at office@vintagestory.at</b><br>
				<b>Code:</b> <?=$codename?><br />
				</div>
			<?php

			self::$usererrorprinted = true;
		}
	}

	/* Debug mode error message */
	public static function printException(Throwable $e, $codename = null) {
		if (ob_get_level() > 0) {
			ob_end_flush();
		}

		if (empty($codename)) {
			$code = $e->getCode();

			if (isset(self::$errorcodes[$code])) {
				$codename = self::$errorcodes[$code];
			} else {
				$codename = $code;
			}
		}


		?>
			<div style="text-align: left; background-color: #fcc; border: 1px solid #600; color: #600; display: block; margin: 1em 0; padding: .33em 6px">
			<b>Error:</b> <?=$codename?><br />
			<b>Message:</b> <?=$e->getMessage()?><br />
			<b>File:</b> <?=$e->getFile()?><br />
			<b>Line:</b> <?=$e->getLine()?><br />
			<b>Stack Trace:</b><pre><?php echo $e->getTraceAsString() ?></pre>
			</div>
		<?php
	}


	/*** The 4 error handlers for Exceptions, Normal Errors, Compile/Fatal Errors, Assertion failures ***/
	public static function HandleException($e) {

		// Prevent infinite recursion and flooding of log files
		if (self::$errorsthrown++ > 10) return;

		if (self::isDebugMode()) {
			self::printException($e, "Caught " . get_class($e));
		} else {
			self::printUserError(E_ERROR, $e, null);
		}

		self::logException($e);
	}


	public static function HandleError($errno, $errstr, $errfile, $errline ) {
		if (($errno & ErrorHandler::$errorreporting) == 0) return;

		// Prevent infinite recursion and flooding of log files
		if (self::$errorsthrown++ > 10) return;

		$e = new ErrorException($errstr, $errno, $errno, $errfile, $errline);

		if (self::isDebugMode()) {
			self::printException($e);
		} else {
			self::printUserError($errno, null, $errstr);
		}

		self::logException($e);
	}


	/* Fatal and Parse errors are not caught by the standard error handler */
	public static function HandleShutDown() {
		// Prevent infinite recursion and flooding of log files
		if (self::$errorsthrown++ > 10) return;

		$error = error_get_last();
		if (!$error) return;
		
		if (@$error['type'] === E_ERROR || @$error['type'] === E_PARSE || @$error['type'] == E_COMPILE_ERROR) {
			$errno   = $error["type"];
			$errfile = $error["file"];
			$errline = $error["line"];
			$errstr  = $error["message"];
			$severity = SEVERITY_FATAL;
			if (@$error['type'] === E_ERROR) {
				$severity = SEVERITY_ERROR;
			}

			$e = new ErrorException($errstr, $errno, $errno, $errfile, $errline);

			if (self::isDebugMode()) {
			   self::printException($e);
			} else {
				self::printUserError($errno);
			}

			self::logException($e, $severity);
		}
	}

	/* Failure of an assertion  */
	public static function HandleAssertFailure($file, $line, $code, $desc = null) {
		$e = new ErrorException("Assertion failure: $desc (Code: $code)", 0, 0, $file, $line);

		// Print error only in debug mode and print nothing in production mode
		if (self::isDebugMode()) {
		   self::printException($e, "Caught assertion failure");
		}

		self::logException($e, SEVERITY_ASSERT);
	}


	/* A timeout has happened */
	public static function HandleSignal($signo) {
		if ($signo == SIGALRM) {
			ErrorHandler::$timeoutstriggered++;
		}

		// First timeout: Log timeout and set alarm again to show timeoutmessage
		if (ErrorHandler::$timeoutstriggered == 1) {
			pcntl_alarm(ErrorHandler::$remainingexecutiontime);

			if (ErrorHandler::$timeoutpath != null && file_exists(ErrorHandler::$timeoutpath)) {
				$filename = ErrorHandler::$timeoutpath . "/" . date("Y-m-d-H_i_s") . "-" . $_SERVER['REMOTE_ADDR'] . ".txt";
				file_put_contents($filename, "");
			}
		}

		// Second tiemout: Show timeout message ("Waitinglounge") and exit
		if (ErrorHandler::$timeoutstriggered == 2) {
			echo ErrorHandler::$timeoutmessage;
			exit();
		}
	}



	public static function logException($e, $severity = null) {
		$code = $e->getCode();

		if (isset(self::$errorcodes[$code])) {
			$codename = self::$errorcodes[$code];
		} else {
			$codename = $code;
		}

		if ($code == E_DEPRECATED) return;

		if (!$severity) $severity = SEVERITY_ERROR;

		$text =
			"Errorcode: {$codename}\n" .
			"Message: " . $e->getMessage() . "\n" .
			"File: " . $e->getFile() . "\n" .
			"Line: " . $e->getLine() . "\n" .
			"Stack Trace: " . $e->getTraceAsString() . "\n";

		logError($severity . ": " . $text);
	}
}
