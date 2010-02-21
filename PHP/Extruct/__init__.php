<?php
# vim:encoding=utf-8:ts=2:sw=2:expandtab
#
# Copyright 2010 AppCove, Inc.                                                
#                                                                            
# Licensed under the Apache License, Version 2.0 (the "License");            
# you may not use this file except in compliance with the License.           
# You may obtain a copy of the License at                                    
#                                                                            
# http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

###################################################################################################
class Extruct
{
  const PrivateSpecName = '_LOCAL_';
  
  # Regular expressions which should match Spec names, and Node Names...
  const REGEX_SPEC_NAME = '/^[0-9a-zA-Z_][0-9a-zA-Z._]*$/';
  const REGEX_NODE_NAME = '/^[a-zA-Z_][0-9a-zA-Z_]*$/';

  public static $Debug = False;

  #==============================================================================================
  public static function Parse($sXML)
  {
    $oXML = simplexml_load_string($sXML);
    
    if(! $oXML)
      throw new Exception("Error parsing xml... (look for warnings).");
    
    $sSum = sha1($sXML);

    //Ensure the root element is named correctly.
    if($oXML->getName() != 'Extruct')
      throw new Exception("The root element of the XML must be <Extruct>, not: " . $oXML->getName());
    
    $RVAL = array();

    foreach($oXML->children() as $oElement)
    {
      $oSpec = new Extruct_Spec($oElement);
      $oSpec->Checksum = $sSum;
      $RVAL[] = $oSpec;
    }
    
    return $RVAL;
  }
  
  #==============================================================================================
  public static function ParseOne($sXML)
  {
    $oXML = simplexml_load_string($sXML);
    
    if(! $oXML)
      throw new Exception("Error parsing xml... (look for warnings).");
    
    $oSpec = new Extruct_Spec($oXML);
    $oSpec->Checksum = sha1($sXML);
    return $oSpec;
  }

  #==============================================================================================
  public static function ParseFile($sPath)
  {
    return self::Parse(file_get_contents($sPath));
  }

  #==============================================================================================
  public static function Serialize($DATA, $SmartArrayType=True)
  {
    $o = new _Extruct_Serialize();
    $o->SmartArrayType = $SmartArrayType;
    return $o->Convert($DATA);
  }
  
  #==============================================================================================
  public static function Unserialize($STREAM)
  {
    $o = new _Extruct_Unserialize();
    return $o->Convert($STREAM);
  }

}



###################################################################################################
class _Extruct_SpecError extends exception
{
  //
  // The internal representation of a Spec parsing error.
  // Not to be used externally.
  //
  
  # A stack representing the position in the Struct
  public $Stack = NULL;
  
  public function __construct($Message, $Attribute=NULL)
  {
    $this->Stack = array(); 
    
    if($Attribute)
      $this->Stack[] = "Attrib:{$Attribute}";

    parent::__construct($Message);
  }

  public function InsertStack($oElement)
  {
    array_unshift
    (
      $this->Stack, 
      $oElement->getName() . ':' . (isset($oElement['Name']) ? (string) $oElement['Name'] : '?')
    );
  }
}

###################################################################################################

class Extruct_SpecError extends exception
{
  //
  //The public exception class of Spec parsing errors.
  //
  
  public $Stack = NULL;

  
  public function __construct(_Extruct_SpecError $oError)
  {
    //
    // Takes a _Extruct_SpecError instance as its only parameter.  
    // Not to be used externally.
    //
    
    $this->Stack = $oError->Stack;

    parent::__construct($oError->getMessage() . " (" . implode('/', $this->Stack));
  }
}


###################################################################################################

class Extruct_BaseNode
{
  /*
  Base class for all nodes on this Spec.

  - BaseNode
    - ScalarNode 
      - NULLNode
      - BoolNode
      - IntNode
      - FloatNode
      - DecimalNode
      - StringNode
      - UnicodeNode
      - BinaryNode
    - VectorNode
      - ListNode
      - DictNode
      - StructNode
  */

  public $Type     = NULL;

  public $Required   = TRUE;
  public $Name     = NULL;
  
  #==============================================================================================
  public function __construct($oSpec, $oElement)
  {
    if(isset($oElement['Name']))
      $this->Name = (string) $oElement['Name'];
    else
      throw new _Extruct_SpecError("Attribute 'Name' is missing.");
    
    if(! preg_match(Extruct::REGEX_NODE_NAME, $this->Name))
      throw new _Extruct_SpecError("Attribute 'Name' is not valid: " . $this->Name);

    if(isset($oElement['Required']))
      $this->Required = (bool) $oElement['Required'];
  }

  #=============================================================================================
  public function VarDump($Indent=0, $NoEnd=False)
  {
    $ending = ($NoEnd ? "" : "\n");
    echo str_repeat("    ", $Indent) . $this->Name . ' (' . get_class($this) . ') ' . ($this->Required ? '' : 'Optional') . $ending;
  }
}

###################################################################################################
class Extruct_ScalarNode extends Extruct_BaseNode
{
  #==============================================================================================
  public function __construct($oSpec, $oElement)
  {
    parent::__construct($oSpec, $oElement);

    if(count($oElement) > 0)
      throw new _Extruct_SpecError("Element must have 0 children elements.");
  }
}

###################################################################################################
class Extruct_NoneNode extends Extruct_ScalarNode
{
  public $Type = 'None';
}

###################################################################################################
class Extruct_BoolNode extends Extruct_ScalarNode
{
  public $Type = 'Bool';
}

###################################################################################################
class Extruct_IntNode extends Extruct_ScalarNode
{
  public $Type = 'Int';
}

###################################################################################################
class Extruct_FloatNode extends Extruct_ScalarNode
{
  public $Type = 'Float';
}

###################################################################################################
class Extruct_DecimalNode extends Extruct_ScalarNode
{
  public $Type = 'Decimal';
}

###################################################################################################
class Extruct_StringNode extends Extruct_ScalarNode
{
  public $Type = 'String';
  
  # The maximum allowable length of a string
  public $MaxLength = NULL;

  #=============================================================================================
  public function __construct($oSpec, $oElement)
  {
    parent::__construct($oSpec, $oElement);

    if(isset($oElement['MaxLength']))
      $this->MaxLength = intval($oElement['MaxLength']);
  }

  #=============================================================================================
  public function VarDump($Indent=0, $NoEnd=False)
  {
    parent::VarDump($Indent, True);
    print "MaxLength=" . $this->MaxLength . "\n";
  }
}

###################################################################################################
class Extruct_UnicodeNode extends Extruct_ScalarNode
{
  public $Type = 'Unicode';
}

###################################################################################################
class Extruct_BinaryNode extends Extruct_ScalarNode
{
  public $Type = 'Binary';
}

###################################################################################################
class Extruct_VectorNode extends Extruct_BaseNode
{
}

###################################################################################################
class Extruct_ListNode extends Extruct_VectorNode
{
  public $Type = 'List';
  
  public $Value = NULL;
  
  #==============================================================================================
  public function __construct($oSpec, $oElement)
  {
    parent::__construct($oSpec, $oElement);

    //TODO: this seems hackish just to get the first child?
    $a = array();
    foreach($oElement as $child)
      $a[] = $child;
    
    if(count($a) != 1)
      throw new _Extruct_SpecError("Vector element must have exactly 1 child element.");

    $this->Value = $oSpec->MakeNode($a[0]);
  }

  #=============================================================================================
  public function VarDump($Indent=0, $NoEnd=False)
  {
    parent::VarDump($Indent);
    $this->Value->VarDump($Indent+1);
  }
}

###################################################################################################
class Extruct_DictNode extends Extruct_VectorNode
{
  public $Type = 'Dict';
  
  public $Key = NULL;
  public $Value = NULL;
  
  #==============================================================================================
  public function __construct($oSpec, $oElement)
  {
    parent::__construct($oSpec, $oElement);
    
    //TODO: this seems hackish just to get the first child?
    $a = array();
    foreach($oElement as $child)
      $a[] = $child;

    if(count($a) != 2)
      throw new _Extruct_SpecError("Vector element must have exactly 2 child elements.");

    if(! in_array($a[0]->getName(), array('Int', 'String', 'Unicode')))
      throw new _Extruct_SpecError("Element key type is invalid: <" . $a->getName() . '>');

    $this->Key = $oSpec->MakeNode($a[0]);
    $this->Value = $oSpec->MakeNode($a[1]);
  }

  #=============================================================================================
  public function VarDump($Indent=0, $NoEnd=False)
  {
    parent::VarDump($Indent);
    $this->Key->VarDump($Indent+1);
    $this->Value->VarDump($Indent+1);
  }
}

###################################################################################################
class Extruct_StructNode extends Extruct_VectorNode
{
  public $Type = 'Struct';
  
  public $Prop = NULL;
  
  
  #==============================================================================================
  public function __construct($oSpec, $oElement)
  {
    parent::__construct($oSpec, $oElement);

    $this->Prop = array();
    
    foreach($oElement as $element)
      $this->Prop[] = $oSpec->MakeNode($element);
  }
      
  #=============================================================================================
  public function VarDump($Indent=0, $NoEnd=False)
  {
    parent::VarDump($Indent);

    foreach($this->Prop as $o)
      $o->VarDump($Indent+1);
  }
}

###################################################################################################


class Extruct_Spec
{  
  # STATIC mapping of all Node tags to Node classes
  public static $TagMap = array
  (
    'None'    => 'Extruct_NoneNode',
    'Bool'    => 'Extruct_BoolNode',
    'Int'    => 'Extruct_IntNode',
    'Float'    => 'Extruct_FloatNode',
    'Decimal'  => 'Extruct_DecimalNode',
    'String'  => 'Extruct_StringNode',
    'Unicode'  => 'Extruct_UnicodeNode',
    'Binary'  => 'Extruct_BinaryNode',
    'List'    => 'Extruct_ListNode',
    'Dict'    => 'Extruct_DictNode',
    'Struct'  => 'Extruct_StructNode',
  );

  
  # A list of Property nodes for this Spec
  public $Prop = NULL;
  
  # The unique ID of this spec
  public $Name = NULL;

  # Holds the checksum of this spec
  # This is set externally
  public $Checksum = "NO-CHECKSUM";
  
  #==============================================================================================
  public function __construct($oElement)
  {
    //
    // Either pass a valid simplexml object represents the <Node> tag, or a 
    // string containing valid <Node> xml (none other).
    //
    
    if(! ($oElement instanceof SimpleXMLElement))
      throw new Exception("Invalid type ".gettype($oElement)." passed to constructor");
    

    try
    {
      if($oElement->getName() != 'Struct')
        throw new _Extruct_SpecError("Invalid tag passed to constructor: <" . $oElement->getName() . ">");

      if(isset($oElement['Name']))
        $this->Name = (string) $oElement['Name'];
      else
        $this->Name = Extruct::PrivateSpecName;

      if(! preg_match(Extruct::REGEX_SPEC_NAME, $this->Name))
        throw new _Extruct_SpecError("Attribute 'Name' is not valid: " . $this->Name);
        
      $this->Prop = array();
      
      foreach($oElement as $element)
        $this->Prop[] = $this->MakeNode($element);
    }
    catch(_Extruct_SpecError $e)
    {
      if(Extruct::$Debug)
        throw $e;
      
      # Convert an internal _SpecError into a public SpecError
      $e->InsertStack($oElement);
      throw new Extruct_SpecError($e);
    }
  }
    
  #==============================================================================================
  public function Convert($DATA, $ConversionType='Native>>Native')
  {
    switch($ConversionType)
    {
      case 'Native>>Native':
        $o = new Extruct_NativeToNative_Convertor($this);
        return $o->Convert($DATA);
      case 'Native>>Stream':
        $o = new Extruct_NativeToStream_Convertor($this);
        return $o->Convert($DATA);
      case 'Stream>>Native':
        $o = new Extruct_StreamToNative_Convertor($this);
        return $o->Convert($DATA);
//      case 'XML>>Native':
//        $o = new Extruct_XMLToNative_Convertor($this);
//        return $o->Convert($DATA);
      case 'Native>>XML':
        $o = new Extruct_NativeToXML_Convertor($this);
        return $o->Convert($DATA);

      default:
        throw new Exception("Invalid value for ConversionType: $ConversionType");
    }
  }

  #==============================================================================================
  public function MakeNode(SimpleXMLElement $oElement)
  {
    //
    // Rather like the super constructor of all nodes.
    //
    
    try
    {
      if(isset(self::$TagMap[$oElement->getName()]))
      {
        $sClass = self::$TagMap[$oElement->getName()];
        return new $sClass($this, $oElement);
      }
      else
      {
        throw new _Extruct_SpecError('Encountered invalid tag: <' . $oElement->getName() . '>');
      }

    }
    catch(_Extruct_SpecError $e)
    {
      $e->InsertStack($oElement);
      throw $e;
    }
    catch(Exception $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new _SpecError($e->getMessage());
    }
  }  
    
  #==============================================================================================
  public function VarDump()
  {
    echo $this->Name . " Data Specification\n";

    foreach($this->Prop as $o)
      $o->VarDump(1);
  }
}

###################################################################################################
class _Extruct_ConversionError extends Exception
{
  //
  // This class represents the internal error stack of an error found while performing a conversion
  //

  public $Stack = NULL;
  public $Node = NULL;
  public $Value = NULL;
  
  public function __construct($oNode, $eValue, $sError)
  {
    $this->Node = $oNode;
    $this->Value = $eValue;
    $this->Stack = array($oNode->Name);

    parent::__construct($sError);
  }

  public function InsertStack($oNode, $Key=NULL)
  {
    //
    // Call this to insert a stack element on to the beginning of the stack.
    //
    
    if(is_null($Key))
    {
      array_unshift($this->Stack, $oNode->Name);
    }
    else
    {
      $Key = strval($Key);

      if(strlen($Key) > 20)
        $Key = substr($Key, 0, 20) . '...';
        
      array_unshift($this->Stack, $oNode->Name . '[' . $Key . ']'); 
    }
  }
}


###################################################################################################
class Extruct_ConversionError extends Exception
{
  //
  // This class is the public face of Convertor object errors.  It is derived from a _ConversionError
  // instance.
  //

  public $Stack = NULL;
  public $Value = NULL;
  
  public function __construct(_Extruct_ConversionError $oError)
  {
    //
    // Takes a _ConversionError instance as its only parameter.  Not to be used externally.
    //
    
    $this->Stack = $oError->Stack;
    $this->Value = $oError->Value;

    parent::__construct($oError->getMessage() . ' (/' . implode('/', $this->Stack) . ')');
  }
}


###################################################################################################
class Extruct_NativeToNative_Convertor
{
  public $Spec = NULL;
  #==============================================================================================
  public function __construct($eSpec)
  {
    if(is_string($eSpec))
    {
      $this->Spec = Extruct::GetSpec($eSpec);
    }
    else if($eSpec instanceof Extruct_Spec)
    {
      $this->Spec = $eSpec;
    }
    else
    {
      throw new Exception("Parameter 1 must be an instance of Extruct_Spec, or a string giving a unique Struct Name.");
    }
  }

  #==============================================================================================
  public function Convert($DATA)
  {
    try
    {
      return $this->_Struct($this->Spec, $DATA);
    }
    catch(_Extruct_ConversionError $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new Extruct_ConversionError($e);
    }
  }
  
  #==============================================================================================
  public function _None($oNode, $DATA)
  {
    return NULL;
  }

  #==============================================================================================
  public function _Bool($oNode, $DATA)
  {
    return (bool) $DATA;
  }

  #==============================================================================================
  public function _Int($oNode, $DATA)
  {
    return (int) $DATA;
  }

  #==============================================================================================
  public function _Float($oNode, $DATA)
  {
    return (float) $DATA;
  }

  #==============================================================================================
  public function _Decimal($oNode, $DATA)
  {
    return (float) $DATA;
  }

  #==============================================================================================
  public function _String($oNode, $DATA)
  {
    $DATA = (string) $DATA;

    if($oNode->MaxLength and strlen($DATA) > $oNode->MaxLength)
      throw new _Extruct_ConversionError($oNode, $DATA, "String length exceeded maximum of {$oNode->MaxLength} bytes.");
    
    return $DATA;
  }
  
  #==============================================================================================
  public function _Unicode($oNode, $DATA)
  {
    throw new _Extruct_ConversionError($oNode, $DATA, "Unicode not supported.");
  }

  #==============================================================================================
  public function _Binary($oNode, $DATA)
  {
    return (string) $DATA;
  }

  #==============================================================================================
  public function _List($oNode, $DATA)
  {
    try
    {
      $oValueNode = $oNode->Value;
      $sValueFunc = "_" . $oValueNode->Type;

      $RVAL = array();
      $i = 0;
      foreach($DATA as $value)
      {
        $i++;
        $RVAL[] = $this->{$sValueFunc}($oValueNode, $value);
      }

      return $RVAL;
    }
    catch(_Extruct_ConversionError $e)
    {
      $e->InsertStack($oNode, $i);
      throw $e;
    }
    catch(Exception $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new _Extruct_ConversionError($oNode, $DATA, get_class($e) . ': ' . $e->getMessage());
    }
  }

  
  #==============================================================================================
  public function _Dict($oNode, $DATA)
  {
    try
    {
      $oKeyNode = $oNode->Key;
      $sKeyFunc = '_' . $oKeyNode->Type;

      $oValueNode = $oNode->Value;
      $sValueFunc = '_' . $oValueNode->Type;
      
      $RVAL = array();

      foreach($DATA as $key => $value)
      {
        $key = $this->{$sKeyFunc}($oKeyNode, $key);
        $RVAL[$key] = $this->{$sValueFunc}($oValueNode, $value);
      }

      return $RVAL;
    }
    catch(_Extruct_ConversionError $e)
    {
      $e->InsertStack($oNode, $key);
      throw $e;
    }
    catch(Exception $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new _Extruct_ConversionError($oNode, $DATA, get_class($e) . ': ' . $e->getMessage());
    }
  }


  #==============================================================================================
  public function _Struct($oNode, $DATA)
  {
    try
    {
      $RVAL = array();
    
      foreach($oNode->Prop as $oPropNode)
      {
      
        $sFunc = '_' . $oPropNode->Type;
        
        if (! isset($DATA[$oPropNode->Name]) and $oPropNode->Required)
          throw new Exception("Key '{$oPropNode->Name}' not found in Struct.");

        else if (! isset($DATA[$oPropNode->Name]) and $oPropNode->Required == 0)
          $RVAL[$oPropNode->Name] == Null;  

        else
          $RVAL[$oPropNode->Name] = $this->{$sFunc}($oPropNode, $DATA[$oPropNode->Name]);
      }

      return $RVAL;
    }
    catch(_Extruct_ConversionError $e)
    {
      $e->InsertStack($oNode);
      throw $e;
    }
    catch(Exception $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new _Extruct_ConversionError($oNode, $DATA, get_class($e) . ': ' . $e->getMessage());
    }
  }
}
  

###################################################################################################
class Extruct_NativeToStream_Convertor extends Extruct_NativeToNative_Convertor
{  
  public $DELIMITER = "|";
  public $Stream = NULL;
  
  #==============================================================================================
  public function Convert($DATA)
  {
    //
    // $DATA is a php array dictionary.  
    // Returns a string of "|" delimited tokens
    //

    $this->Stream = array($this->Spec->Checksum);
    parent::Convert($DATA);
    return implode($this->DELIMITER, $this->Stream);
  }

  #==============================================================================================
  public function _None($oNode, $DATA)
  {
    $this->Stream[] = '<None>';
  }

  #==============================================================================================
  public function _Bool($oNode, $DATA)
  {
    $DATA = parent::_Int($oNode, $DATA);
    $this->Stream[] = (string) $DATA;
  }

  #==============================================================================================
  public function _Int($oNode, $DATA)
  {
    $DATA = parent::_Int($oNode, $DATA);
    $this->Stream[] = (string) $DATA;
  }

  #==============================================================================================
  public function _Float($oNode, $DATA)
  {
    $DATA = parent::_Float($oNode, $DATA);
    $this->Stream[] = (string) $DATA;
  }

  #==============================================================================================
  public function _Decimal($oNode, $DATA)
  {
    $DATA = parent::_Decimal($oNode, $DATA);
    $this->Stream[] = (string) $DATA;
  }

  #==============================================================================================
  public function _String($oNode, $DATA)
  {
    $DATA = parent::_String($oNode, $DATA);
    $this->Stream[] = base64_encode((string) $DATA);
    #$this->Stream[] = (string) $DATA;
  }

  #==============================================================================================
  public function _Unicode($oNode, $DATA)
  {
    throw new _Extruct_ConversionError($oNode, $DATA, "Unsupported data type" . gettype($DATA));
  }

  #==============================================================================================
  public function _Binary($oNode, $DATA)
  {
    $DATA = parent::_Binary($oNode, $DATA);
    $this->Stream[] = base64_encode((string) $DATA);
  }

  #==============================================================================================
  public function _List($oNode, $DATA)
  {
    $this->Stream[] = '<List>';
    parent::_List($oNode, $DATA);
    $this->Stream[] = '</List>';
  }

  #==============================================================================================
  public function _Dict($oNode, $DATA)
  {
    $this->Stream[] = '<Dict>';
    parent::_Dict($oNode, $DATA);
    $this->Stream[] = '</Dict>';
  }
  
  #==============================================================================================
  public function _Struct($oNode, $DATA)
  {
    $this->Stream[] = '<Struct>';
    parent::_Struct($oNode, $DATA);
    $this->Stream[] = '</Struct>';
  }
}


###################################################################################################
class Extruct_StreamToNative_Convertor extends Extruct_NativeToNative_Convertor
{
  public $DELIMITER = "|";
  public $Stream = NULL;
  
  #==============================================================================================
  public function Convert($DATA)
  {
    //
    // This time, data is a $$this->DELIMITER delimited string of tokens
    // Returns a native array object (hopefully)
    //

    $this->Stream = explode($this->DELIMITER, $DATA);

    //The first token is the checksum
    $Checksum = $this->Stream[0];
    if($Checksum != $this->Spec->Checksum)
      throw new Exception("Stream checksum '{$Checksum}' does not match spec checksum '{$this->Spec->Checksum}' for spec '{$this->Spec->Name}'.");
  
    //We use the next() function to move the pointer to the [1]th value and return it
    return parent::Convert(next($this->Stream));
  }

  
  #==============================================================================================
  public function _String($oNode, $DATA)
  {
    return parent::_String($oNode, base64_decode($DATA));
    #return parent::_String($oNode, $DATA);
  }
  
  #==============================================================================================
  public function _List($oNode, $DATA)
  {
    try
    {
      if($DATA != '<List>')
        throw new Exception("List not starting with a <List> token.");
      
      $oValueNode = $oNode->Value;
      $sValueFunc = "_" . $oValueNode->Type;

      $RVAL = array();
      $i = 0;
      
      while(True)
      {
        $value = next($this->Stream);
        if($value == '</List>')
          break;

        $i++;
        $RVAL[] = $this->{$sValueFunc}($oValueNode, $value);
      }

      return $RVAL;
    }
    catch(_Extruct_ConversionError $e)
    {
      $e->InsertStack($oNode, $i);
      throw $e;
    }
    catch(Exception $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new _Extruct_ConversionError($oNode, $DATA, get_class($e) . ': ' . $e->getMessage());
    }
  }

  
  #==============================================================================================
  public function _Dict($oNode, $DATA)
  {
    try
    {
      if($DATA != '<Dict>')
        throw new Exception("Dict not starting with a <Dict> token.");
      
      $oKeyNode = $oNode->Key;
      $sKeyFunc = '_' . $oKeyNode->Type;

      $oValueNode = $oNode->Value;
      $sValueFunc = '_' . $oValueNode->Type;
      
      $RVAL = array();

      while(True)
      {
        $key = next($this->Stream);
        if($key == '</Dict>')
          break;
        $value = next($this->Stream);

        $key = $this->{$sKeyFunc}($oKeyNode, $key);
        $RVAL[$key] = $this->{$sValueFunc}($oValueNode, $value);
      }

      return $RVAL;
    }
    catch(_Extruct_ConversionError $e)
    {
      $e->InsertStack($oNode, $key);
      throw $e;
    }
    catch(Exception $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new _Extruct_ConversionError($oNode, $DATA, get_class($e) . ': ' . $e->getMessage());
    }
  }


  #==============================================================================================
  public function _Struct($oNode, $DATA)
  {
    try
    {
      if($DATA != '<Struct>')
        throw new Exception("Struct not starting with a <Struct> token.");
      
      $RVAL = array();
    
      foreach($oNode->Prop as $oPropNode)
      {
        $sFunc = '_' . $oPropNode->Type;
        
        $value = next($this->Stream);

        $RVAL[$oPropNode->Name] = $this->{$sFunc}($oPropNode, $value);
      }

      # Discard the final '</Struct>' at the end of the struct.
      next($this->Stream);

      return $RVAL;
    }
    catch(_Extruct_ConversionError $e)
    {
      $e->InsertStack($oNode);
      throw $e;
    }
    catch(Exception $e)
    {
      if(Extruct::$Debug)
        throw $e;
      throw new _Extruct_ConversionError($oNode, $DATA, get_class($e) . ': ' . $e->getMessage());
    }
  }
  
}

###########################################################################################

class _Extruct_Serialize
{
  #
  # Stream Format a simple token stream.
  #
  # Stream:
  #  start-token | (scalar or vectors ...) | end-token
  #
  # Scalar:
  #  name | type | value
  #
  # Scalar (None):
  #  name | type
  #
  # Array:
  #  name | type | { | [type | key | type | value [| ...]] | }
  #
  # N : Null
  # B : Bool
  # I : Int
  # F : Float
  # D : Decimal
  # S : String
  # L : List
  # T : Tuple
  # M : Dict/Map
  # A : Array
  #
  
  const VERSION = 1;
  
  const STREAM_START = '[[1';
  const STREAM_END = ']]';
  
  public $SmartArrayType = True;

  public function Convert($DATA)
  {
    $this->Stream = array();
    
    $this->Stream[] = self::STREAM_START;
    $this->Value($DATA);
    $this->Stream[] = self::STREAM_END;
    
    return implode('|', $this->Stream);
  }

  public function Value($DATA)
  {
    switch(gettype($DATA))
    {
      case 'NULL':
        $this->Stream[] = 'N';
        return;

      case 'boolean':
        $this->Stream[] = 'B';
        $this->Stream[] = ($DATA ? '1' : '0');
        return;

      case 'integer':
        $this->Stream[] = 'I';
        $this->Stream[] = (string) $DATA;
        return;
      
      case 'double':
        $this->Stream[] = 'F';
        $this->Stream[] = (string) $DATA;
        return;
      
      case 'string':
        $this->Stream[] = 'S';
        $this->Stream[] = base64_encode($DATA);
        return;

      case 'array':
        if($this->SmartArrayType)
        {
          foreach(array_keys($DATA) as $k)
          {
            if(is_string($k))
            {
              $this->_DictType($DATA);
              return;          
            }
          }

          $this->_ListType($DATA);
          return;
        }
        
        $this->_ArrayType($DATA);
        return;
            
      default:
        throw new ValueError("Unsupported type '" . gettype($DATA) . "'.");
    };
  }
  
  public function _ListType($DATA)
  {
    $this->Stream[] = 'L';
    $this->Stream[] = '[';
    foreach($DATA as $v)
    {
      $this->Value($v);
    }

    $this->Stream[]  = ']';
  }

  public function _DictType($DATA)
  {
    $this->Stream[] = 'M';
    $this->Stream[] = '{';
    foreach($DATA as $k => $v)
    {
      $this->Value($k);
      $this->Value($v);
    }

    $this->Stream[]  = '}';
  }

  public function _ArrayType($DATA)
  {
    $this->Stream[] = 'A';
    $this->Stream[] = '{';
    foreach($DATA as $k => $v)
    {
      $this->Value($k);
      $this->Value($v);
    }

    $this->Stream[]  = '}';
  }
}  


###################################################################################################
class _Extruct_Unserialize
{
  const VERSION = 1;
  
  const STREAM_START = '[[1';
  const STREAM_END = ']]';
  
  public function Convert($STREAM)
  {
    $this->Stream = explode('|', $STREAM);

    if(rtrim(array_pop($this->Stream)) != self::STREAM_END)
      throw new ValueError("Unknown stream end token");
    
    if(ltrim($this->Stream[0]) != self::STREAM_START)
      throw new ValueError("Unknown stream start token");
    
    # Call the Value Function with the first datatype encountered
    return $this->Value(next($this->Stream));
  }
  
  public function Value($DATATYPE)
  {
    switch($DATATYPE)
    {
      case 'N':
        return NULL;

      case 'B':
        return (next($this->Stream) == '0' ? False : True);

      case 'I':
        return (int) next($this->Stream);
      
      case 'F':
      case 'D':
        return (float) next($this->Stream);
      
      case 'S':
        return base64_decode(next($this->Stream));

      case 'L':
      case 'T':
        return $this->_ListType();
      
      case 'M':
      case 'A':
        return $this->_DictType();
        
      default:
        throw new TypeError("No type conversion for Type '$DATATYPE'.");
    }
  }

  # For all of the following functions, they need to read thier value; their type has already been read
  
  public function _ListType()
  {
    switch(next($this->Stream))
    {
      case '[': break;
      case '(': break;
      default: throw new ValueError("Invalid list start token.");
    }
    
    $RVAL = array();

    while(True)
    {
       $t = next($this->Stream);
      if($t == ']')
        break;

      $RVAL[] = $this->Value($t);
    }

    return $RVAL;
  }

  public function _DictType()
  {
    if(next($this->Stream) != '{')
      throw new ValueError("Invalid array/dict/map start token.");
    
    $RVAL = array();

    while(True)
    {
      $kt = next($this->Stream);
      if($kt == '}')
        break;

      if($kt != 'I' and $kt != 'S')
        throw new ValueError("Dictionary keys must be String or Int, not: $kt");

      # The key should be string or int
      $key = $this->Value($kt);
      
      # Get the value type -> pass it to Value() -> Assign to dict
      $RVAL[$key] = $this->Value(next($this->Stream));
    }

    return $RVAL;
  }
}


