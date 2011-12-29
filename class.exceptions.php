<?php
/* A simple implementation of a custom exceptions */

interface IException
{
    /* Protected methods inherited from Exception class */
    public function getMessage();                 // Exception message
    public function getCode();                    // User-defined Exception code
    public function getFile();                    // Source filename
    public function getLine();                    // Source line
    public function getTrace();                   // An array of the backtrace()
    public function getTraceAsString();           // Formated string of trace
   
    /* Overrideable methods inherited from Exception class */
    public function __toString();                 // formated string for display
    public function __construct($message = null, $code = 0);
}

abstract class CustomException extends Exception implements IException
{
    protected $message = 'Unknown exception';     // Exception message
    private   $string;                            // Unknown
    protected $code    = 0;                       // User-defined exception code
    protected $file;                              // Source filename of exception
    protected $line;                              // Source line of exception
    private   $trace;                             // Unknown

    public function __construct($message = null, $code = 0)
    {
        if (!$message) {
            throw new $this('Unknown '. get_class($this));
        }
        parent::__construct($message, $code);
    }
   
    public function __toString()
    {
        return get_class($this) . " '{$this->message}' in {$this->file}({$this->line})\n"
                                . "{$this->getTraceAsString()}";
    }
}

/*
<?php
// Here's a test that shows that all information is properly preserved throughout the backtrace.
// class TestException extends CustomException {}
function exceptionTest()
{
    try {
        throw new TestException();
    }
    catch (TestException $e) {
        echo "Caught TestException ('{$e->getMessage()}')\n{$e}\n";
    }
    catch (Exception $e) {
        echo "Caught Exception ('{$e->getMessage()}')\n{$e}\n";
    }
}

echo '<pre>' . exceptionTest() . '</pre>';

/*
Here's a sample output:

Caught TestException ('Unknown TestException')
TestException 'Unknown TestException' in C:\xampp\htdocs\CustomException\CustomException.php(31)
#0 C:\xampp\htdocs\CustomException\ExceptionTest.php(19): CustomException->__construct()
#1 C:\xampp\htdocs\CustomException\ExceptionTest.php(43): exceptionTest()
#2 {main}
*/
?>
