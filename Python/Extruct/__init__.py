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
try:
  # Appstruct is currently required to handle <DateTime> type
  from AppStruct.Date import ISOToDate, ISOToDateTime, UTC
except ImportError:
  pass

# Import types
NoneType = type(None)
BooleanType = bool
IntType = int
FloatType = float
StringType = str
BytesType = bytes
ListType = list
TupleType = tuple
DictType = dict
from decimal import Decimal as DecimalType
from collections import OrderedDict
from datetime import datetime as DateTimeType
from datetime import date as DateType

# Other code needed
from base64 import b64encode, b64decode
from xml.etree import cElementTree as ElementTree
from decimal import Decimal
import re

Debug = False
PrivateSpecName = '_LOCAL_'

# A regular expression which should match Spec names...
REGEX_SPEC_NAME = re.compile('^[0-9a-zA-Z_][0-9a-zA-Z._]*$')
REGEX_NODE_NAME = re.compile('^[a-zA-Z_][0-9a-zA-Z_]*$')

#TODO: Remove this
REGEX_NODE_NAME = REGEX_SPEC_NAME

###################################################################################################
class aadict(dict):
  __slots__ = ()
  __getattr__ = dict.__getitem__
  __setattr__ = dict.__setitem__
  __delattr__ = dict.__delitem__

###################################################################################################
class ParseError(Exception):
  pass

###################################################################################################
def Parse(sXML):

  oXML = ElementTree.fromstring(sXML)

  if oXML.tag != 'Extruct':
    raise ParseError("The root element of the XML must be <Extruct>, not: %s" % oXML.tag)

  RVAL = []

  for oElement in oXML:
    oSpec = Spec(oElement)
    RVAL.append(oSpec)

  return RVAL


###################################################################################################
def ParseOne(sXML):

  oXML = ElementTree.fromstring(sXML)

  oSpec = Spec(oXML)

  return oSpec

###################################################################################################
def ParseFile(sPath):
  try:
    return Parse(open(sPath, 'r').read())
  except Exception as e:
    raise ParseError("%s encountered while parsing '%s': %s" % (e.__class__.__name__, sPath, e.args[0]))


###################################################################################################
def ParseFileForNames(sPath):

  try:
    oXML = ElementTree.parse(sPath).getroot()

    if oXML.tag != 'Extruct':
      raise ValueError("The root element of the XML must be <Extruct>, not: %s" % oXML.tag)

    RVAL = []
    for node in oXML:
      RVAL.append(node.attrib['Name'])

    return RVAL

  except Exception as e:
    raise ParseError("%s encountered while parsing '%s': %s" % (e.__class__.__name__, sPath, e.args[0]))


###################################################################################################
###################################################################################################
class _SpecError(Exception):
  """
  The internal representation of a Spec parsing error
  """

  # A stack representing the position in the Struct
  Stack = None

  def __init__(self, Message, Attribute=None):
    self.Stack = []

    if Attribute != None:
      self.Stack.append("Attrib:%s" % Attribute)

    Exception.__init__(self, Message)

  def InsertStack(self, oElement):
    self.Stack.insert(0, "%s:%s" % (oElement.tag, oElement.attrib['Name'] if 'Name' in oElement.attrib else '?'))

###################################################################################################

class SpecError(Exception):
  """
  The public exception class of Spec parsing errors.
  """

  Stack = None

  def __init__(self, oError):
    """
    Takes a _SpecError instance as its only parameter.  Not to be used externally.
    """

    if not isinstance(oError, _SpecError):
      raise TypeError("Invalid type '%s' passed to constructor." % type(oError))

    self.Stack = tuple(oError.Stack)

    Exception.__init__(self, "%s (/%s)" % (oError.args[0], str.join("/", self.Stack)))


###################################################################################################

class BaseNode(object):
  """
  Base class for all nodes on this Spec.

  - BaseNode
    - ScalarNode
      - NoneNode
      - BoolNode
      - IntNode
      - FloatNode
      - DecimalNode
      - StringNode
      - BytesNode
      - VectorNode
      - ListNode
      - DictNode
      - StructNode
  """

  # STATIC: This must be overridden on base classes.

  Type    = None
  Name    = None

  Nullable  = False

  # Despite the fact that only scalars can have defaults, we are relying
  # on always having a Default attribute available, so it is defaulted here.
  Default   = None

  #==============================================================================================
  def __init__(self, oSpec, oElement):

    try:
      self.Name = oElement.attrib['Name']

      if 'Nullable' in oElement.attrib:

        if oElement.attrib['Nullable'] == '1':
          self.Nullable = True

        elif oElement.attrib['Nullable'] == '0':
          self.Nullable = False

        else:
          raise _SpecError("Nullable attribute must be either '1' or '0'")

    except KeyError:
      raise _SpecError("Attribute 'Name' is missing.")

    if not REGEX_NODE_NAME.match(self.Name):
      raise _SpecError("Attribute 'Name' is not valid: %s" % self.Name)


  #=============================================================================================
  def VarDump(self, Indent=0, NoEnd=False):
    ending = "" if NoEnd else "\n"

    print("%s[%s] Property at (%s) %s%s" % (" "*Indent, self.Name, self.__class__.__name__, "" if not self.Nullable else "Nullable", ending), end=' ')

###################################################################################################
class ObjectNode(BaseNode):
  Type = 'Object'

  #==============================================================================================
  def __init__(self, oSpec, oElement):
    BaseNode.__init__(self, oSpec, oElement)

    if len(oElement) > 0:
      raise _SpecError("Element must have 0 children elements.")

###################################################################################################
class NoneNode(BaseNode):
  Nullable = True
  Type = 'None'

###################################################################################################
class ScalarNode(BaseNode):


  #==============================================================================================
  def __init__(self, oSpec, oElement):
    BaseNode.__init__(self, oSpec, oElement)

    if 'Default' in oElement.attrib:
      self.Default = oElement.attrib['Default']

    if len(oElement) > 0:
      raise _SpecError("Element must have 0 children elements.")



###################################################################################################
class BoolNode(ScalarNode):
  Type = 'Bool'

###################################################################################################
class IntNode(ScalarNode):
  Type = 'Int'

###################################################################################################
class FloatNode(ScalarNode):
  Type = 'Float'

###################################################################################################
class DecimalNode(ScalarNode):
  Type = 'Decimal'

###################################################################################################
class DateTimeNode(ScalarNode):
  Type = 'DateTime'

###################################################################################################
class DateNode(ScalarNode):
  Type = 'Date'

###################################################################################################
class StringNode(ScalarNode):
  Type = 'String'

  # The maximum allowable length of a string
  MaxLength = None
  Trim = True

  #=============================================================================================
  def __init__(self, oSpec, oElement):
    ScalarNode.__init__(self, oSpec, oElement)

    try:
      if 'MaxLength' in oElement.attrib:
        self.MaxLength = int(oElement.attrib['MaxLength'])
    except Exception as e:
      raise _SpecError(e.args[0], 'MaxLength')
      
    if 'Trim' in oElement.attrib:
      if oElement.attrib['Trim'] == '0':
        self.Trim = False
      elif oElement.attrib['Trim'] == '1':
        self.Trim = True
      else:
        raise _SpecError("Trim attribute must be '1' or '0'")
	  


  #=============================================================================================
  def VarDump(self, Indent=0):
    ScalarNode.VarDump(self, Indent, NoEnd=True)
    print("MaxLength=%s" % self.MaxLength)
    print("Trim=%s" % self.Trim)


###################################################################################################
class BytesNode(ScalarNode):
  Type = 'Bytes'

###################################################################################################
class VectorNode(BaseNode):
  pass

###################################################################################################
class ListNode(VectorNode):
  Type = 'List'

  Value = None

  #==============================================================================================
  def __init__(self, oSpec, oElement):
    VectorNode.__init__(self, oSpec, oElement)

    if len(oElement) != 1:
      raise _SpecError("Vector element must have exactly 1 child element.")

    self.Value = oSpec.MakeNode(oElement[0])

  #=============================================================================================
  def VarDump(self, Indent=0):
    VectorNode.VarDump(self, Indent)
    self.Value.VarDump(Indent+1)


###################################################################################################
class DictNode(VectorNode):
  Type = 'Dict'

  Key = None
  Value = None

  #==============================================================================================
  def __init__(self, oSpec, oElement):
    VectorNode.__init__(self, oSpec, oElement)

    if len(oElement) != 2:
      raise _SpecError("Vector element must have exactly 2 child elements.")

    if oElement[0].tag not in ('Int', 'String', 'Bytes'):
      raise _SpecError("Element key type is invalid: <%s>" % oElement[0].tag)

    self.Key = oSpec.MakeNode(oElement[0])
    self.Value = oSpec.MakeNode(oElement[1])

  #=============================================================================================
  def VarDump(self, Indent=0):
    VectorNode.VarDump(self, Indent)
    self.Key.VarDump(Indent+1)
    self.Value.VarDump(Indent+1)


###################################################################################################
class StructNode(VectorNode):
  Type = 'Struct'

  Prop = None


  #==============================================================================================
  def __init__(self, oSpec, oElement):
    VectorNode.__init__(self, oSpec, oElement)

    self.Prop = []

    for element in oElement:
      self.Prop.append(oSpec.MakeNode(element))

  #=============================================================================================
  def VarDump(self, Indent=0):
    VectorNode.VarDump(self, Indent)

    for o in self.Prop:
      o.VarDump(Indent+1)

###################################################################################################


class Spec(object):

  # STATIC mapping of all Node tags to Node classes
  TagMap = {
    'Object'  : ObjectNode,
    'None'    : NoneNode,
    'Bool'    : BoolNode,
    'Int'     : IntNode,
    'Float'   : FloatNode,
    'Decimal' : DecimalNode,
    'Date'    : DateNode,
    'DateTime': DateTimeNode,
    'String'  : StringNode,
    'Bytes'   : BytesNode,
    'List'    : ListNode,
    'Dict'    : DictNode,
    'Struct'  : StructNode,
  }


  # The name of this sepc object is always the name of the root node
  def Name_get(self):
    return self.ROOT.Name;
  def Name_set(self, value):
    self.ROOT.Name = value
  Name = property(Name_get, Name_set)

  #==============================================================================================
  def __init__(self, oElement, Checksum=None):
    """
    Either pass a valid xml.etree.ElementTree.Element that represents the <Node> tag, or a
    string containing valid <Node> xml (none other).
    """

    #------------------------------------------------------------------------------------------
    if not ElementTree.iselement(oElement):
      raise TypeError("Invalid type '%s' passed to constructor." % type(XML))


    #------------------------------------------------------------------------------------------
    try:
      self.ROOT = self.MakeNode(oElement)

    except _SpecError as e:
      if Debug: raise
      # Convert an internal _SpecError into a public SpecError
      e.InsertStack(oElement)
      raise SpecError(e)

  #==============================================================================================
  def Convert(self, DATA, ConversionType="Native>>Native"):
    if ConversionType == 'Native>>Native':
      return NativeToNative_Convertor(self).Convert(DATA)
    else:
      raise ValueError("Invalid value for ConversionType: %s" % str(ConversionType))


  #==============================================================================================
  def MakeNode(self, oElement):
    """
    Rather like the super constructor of all nodes.
    """

    try:
      try:
        return self.TagMap[oElement.tag](self, oElement)
      except KeyError:
        raise _SpecError('Encountered invalid tag: <%s>.' % oElement.tag)

    except _SpecError as e:
      e.InsertStack(oElement)
      raise

    except Exception as e:
      if Debug: raise
      raise _SpecError(e.args[0])


  #==============================================================================================
  def VarDump(self):
    print()
    print('`%s` Data Specification' % self.Name)
    print()
    self.ROOT.VarDump(2)


###################################################################################################
class _ConversionError(Exception):
  """
  This class represents the internal error stack of an error found while performing a conversion
  """

  Stack = None
  Node = None
  Value = None

  def __init__(self, oNode, eValue, sError):

    self.Node = oNode
    self.Value = eValue
    self.Stack = [oNode.Name]

    Exception.__init__(self, sError)

  def InsertStack(self, oNode, Key=None):
    """
    Call this to insert a stack element on to the beginning of the stack.
    """

    if Key == None:
      self.Stack.insert(0, oNode.Name)
    else:
      Key = str(Key)
      if len(Key) > 20: Key = Key[:20] + "..."
      self.Stack.insert(0, "%s[%s]" % (oNode.Name, Key))


###################################################################################################
class ConversionError(Exception):
  """
  This class is the public face of Convertor object errors.  It is derived from a _ConversionError
  instance.
  """

  Stack = None
  Value = None

  def __init__(self, oError):
    """
    Takes a _ConversionError instance as its only parameter.  Not to be used externally.
    """

    if not isinstance(oError, _ConversionError):
      raise TypeError("Invalid type '%s' passed to constructor." % type(oError))

    self.Stack = tuple(oError.Stack)
    self.Value = oError.Value

    Exception.__init__(self, "%s (/%s)" % (oError.args[0], str.join("/", self.Stack)))

###################################################################################################
class NativeToNative_Convertor(object):

  Spec = None

  #==============================================================================================
  def __init__(self, eSpec):
    # We are dealing directly with a spec
    if not isinstance(eSpec, Spec):
      raise TypeError("Parameter 1 must be an instance of %s." % Spec)

    self.Spec = eSpec

  #==============================================================================================
  def Convert(self, DATA):
    # Depending on the type node, get the initial function to call
    oFunc = getattr(self, "_"+self.Spec.ROOT.Type)

    try:
      # call the conversion function with the Spec.ROOT, and the passed DATA
      return oFunc(self.Spec.ROOT, DATA)
    except _ConversionError as e:
      if Debug: raise
      raise ConversionError(e)

  #==============================================================================================
  def _Object(self, oNode, DATA):
    return DATA
  
  #==============================================================================================
  def _None(self, oNode, DATA):
    return None

  #==============================================================================================
  def _Bool(self, oNode, DATA):
    try:
      return bool(DATA)
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])

  #==============================================================================================
  def _Int(self, oNode, DATA):
    try:
      return int(DATA)
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])

  #==============================================================================================
  def _Float(self, oNode, DATA):
    try:
      return float(DATA)
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])

  #==============================================================================================
  def _Decimal(self, oNode, DATA):
    try:
      # Cannot convert float to Decimal. First convert the float to a string.
      if isinstance(DATA, float):
        return Decimal(str(DATA))
      else:
        return Decimal(DATA)
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])

  #==============================================================================================
  def _Date(self, oNode, DATA):
    try:
      if type(DATA) == DateType:
        return DATA      
      elif isinstance(DATA, DateTimeType):
        return DateType(DATA.year, DATA.month, DATA.day)
      elif isinstance(DATA, str):
        return ISOToDate(DATA)
      else:
        raise TypeError('Cannot covert type ' + str(type(DATA)) + ' to DateType.')
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])
  
  #==============================================================================================
  def _DateTime(self, oNode, DATA):
    try:
      if type(DATA) == DateTimeType:
        return DATA
      elif isinstance(DATA, DateType):
        return DateTimeType(DATA.year, DATA.month, DATA.day, 0, 0, 0, tzinfo=UTC)
      elif isinstance(DATA, str):
        return ISOToDateTime(DATA)
      else:
        raise TypeError('Cannot covert type ' + str(type(DATA)) + ' to DateTimeType.')
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])

  #==============================================================================================
  def _String(self, oNode, DATA):
    try:
      DATA = str(DATA)
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])

    if oNode.Trim:
      DATA = DATA.strip()

    if oNode.MaxLength and len(DATA) > oNode.MaxLength:
      raise _ConversionError(oNode, DATA, "String length exceeded maximum of %s bytes." % oNode.MaxLength)

    return DATA

  #==============================================================================================
  def _Bytes(self, oNode, DATA):
    try:
      DATA = bytes(DATA)
    except Exception as e:
      raise _ConversionError(oNode, DATA, e.args[0])

    return DATA


  #==============================================================================================
  def _List(self, oNode, DATA):
    try:
      oValueNode = oNode.Value
      oValueFunc = getattr(self, "_"+oValueNode.Type)

      RVAL = []

      i = 0
      for value in DATA:
        i += 1
        RVAL.append(oValueFunc(oValueNode, value))

      return RVAL

    except _ConversionError as e:
      e.InsertStack(oNode, i)
      raise

    except Exception as e:
      if Debug: raise
      raise _ConversionError(oNode, DATA, "%s: %s" % (e.__class__.__name__, e.args[0]))


  #==============================================================================================
  def _Dict(self, oNode, DATA):
    try:
      oKeyNode = oNode.Key

      oKeyFunc = getattr(self, "_"+oKeyNode.Type)

      oValueNode = oNode.Value
      oValueFunc = getattr(self, "_"+oValueNode.Type)

      RVAL = dict()

      for key in DATA:
        value = DATA[key]

        # New key, value
        key = oKeyFunc(oKeyNode, key)
        RVAL[key] = oValueFunc(oValueNode, value)

      return RVAL

    except _ConversionError as e:
      e.InsertStack(oNode, key)
      raise

    except Exception as e:
      if Debug: raise
      raise _ConversionError(oNode, DATA, "%s: %s" % (e.__class__.__name__, e.args[0]))

  #==============================================================================================
  def _Struct(self, oNode, DATA):
    try:

      RVAL = aadict()

      for oPropNode in oNode.Prop:
        oFunc = getattr(self, "_"+oPropNode.Type)

        try:
          value = DATA[oPropNode.Name]

        except KeyError as e:
          value = oPropNode.Default

        if value == None:
          if not oPropNode.Nullable:
            raise KeyError("[%s] must be set, Nullable or Defaulted" % oPropNode.Name)
          else:
            RVAL[oPropNode.Name] = None
        else:
          RVAL[oPropNode.Name] = oFunc(oPropNode, value)


      return RVAL

    except _ConversionError as e:
      e.InsertStack(oNode)
      raise

    except Exception as e:
      if Debug: raise
      raise _ConversionError(oNode, DATA, "%s: %s" % (e.__class__.__name__, e.args[0]))

###################################################################################################



class Serialize(object):
  """
  Stream Format a simple token stream.

  Stream:
    start-token | (scalar or vectors ...) | end-token

  Scalar:
    name | type | value

  Scalar (None):
    name | type

  List:
    name | type | [ | [type | value [| ...]] | ]

  Tuple:
    name | type | ( | [type | value [| ...]] | )

  Dict/Map/Array:
    name | type | { | [type | key | type | value [| ...]] | }

  N : Null
  B : Bool
  I : Int
  F : Float
  D : Decimal
  S : String
  Y : Bytes
  L : List
  T : Tuple
  M : Dict/Map

  """

  VERSION = 1

  STREAM_START = '[[%i' % VERSION
  STREAM_END = ']]'


  def __new__(cls, DATA):
    self = object.__new__(cls)
    TokenList = []
    self.add = TokenList.append

    self.add(self.STREAM_START)
    self.Value(DATA)
    self.add(self.STREAM_END)

    return str.join("|", TokenList)

  def Value(self, DATA):
    try:
      self.Map[type(DATA)](self, DATA)
    except KeyError:
      raise TypeError("No type conversion defined for Type %s (value=%s)" % (type(DATA), str(DATA)))

  # For all of the following functions, assume that the name has already been appended

  def _NoneType(self, DATA):
    self.add('N')

  def _BooleanType(self, DATA):
    self.add('B')
    self.add('1' if DATA else '0')

  def _IntType(self, DATA):
    self.add('I')
    self.add(str(DATA))

  def _FloatType(self, DATA):
    self.add('F')
    self.add(str(DATA))

  def _DecimalType(self, DATA):
    self.add('D')
    self.add(str(DATA))

  def _StringType(self, DATA):
    self.add('S')
    self.add(DATA.encode())

  def _BytesType(self, DATA):
    self.add('S')
    self.add(b64encode(DATA))

  def _ListType(self, DATA):
    self.add('L')
    self.add('[')

    for v in DATA:
      self.Value(v)

    self.add(']')

  def _TupleType(self, DATA):
    self.add('T')
    self.add('(')

    for v in DATA:
      self.Value(v)

    self.add(')')

  def _DictType(self, DATA):
    self.add('M')
    self.add('{')
    for k in DATA:
      if not isinstance(k, (StringType, IntType)):
        raise TypeError("Dictionary keys must be String or Int, not: %s" % type(k))

      self.Value(k)
      self.Value(DATA[k])

    self.add('}')

  Map = {
    NoneType  : _NoneType,
    BooleanType : _BooleanType,
    IntType   : _IntType,
    FloatType : _FloatType,
    DecimalType : _DecimalType,
    StringType  : _StringType,
    BytesType : _BytesType,
    TupleType : _TupleType,
    ListType  : _ListType,
    DictType  : _DictType,
    }


###################################################################################################
class Unserialize(object):

  VERSION = 1

  STREAM_START = "[[%{0}".format(VERSION)
  STREAM_END = ']]'


  def __new__(cls, STREAM):
    self = object.__new__(cls)
    TokenList = STREAM.split('|')

    if TokenList.pop().rstrip() != self.STREAM_END:
      raise ValueError("Unknown stream end token")

    self.next = iter(TokenList).__next__

    if self.next().lstrip() != self.STREAM_START:
      raise ValueError("Unknown stream start token")

    # Call the Value Function with the first datatype encountered
    return self.Value(next(self))


  def Value(self, DATATYPE):
    try:
      return self.Map[DATATYPE](self)
    except KeyError:
      raise TypeError("No type conversion defined for Type %s (value=%s)" % (type(DATA), str(DATA)))

  # For all of the following functions, they need to read thier value; their type has already been read

  def _NoneType(self):
    return None

  def _BooleanType(self):
    return False if next(self) == '0' else True

  def _IntType(self):
    return IntType(next(self))

  def _FloatType(self):
    return FloatType(next(self))

  def _DecimalType(self):
    return DecimalType(next(self))

  def _StringType(self):
    return self.next().decode()

  def _BytesType(self):
    return b64decode(self.next())

  def _ListType(self):
    if next(self) != '[':
      raise ValueError("Invalid list start token.")

    RVAL = []

    while True:
      t = next(self)
      if t == ']':
        break

      RVAL.append(self.Value(t))

    return RVAL


  def _TupleType(self):
    if next(self) != '(':
      raise ValueError("Invalid tuple start token.")

    RVAL = []

    while True:
      t = next(self)
      if t == ')':
        break

      RVAL.append(self.Value(t))

    return TupleType(RVAL)

  def _DictType(self):
    if next(self) != '{':
      raise ValueError("Invalid dict start token.")

    RVAL = {}

    while True:
      kt = next(self)
      if kt == '}':
        break

      if kt not in ('I', 'S'):
        raise ValueError("Dictionary keys must be String or Int, not: %s" % kt)

      # The key should be string or int
      key = self.Value(kt)

      # Get the value type -> pass it to Value() -> Assign to dict
      RVAL[key] = self.Value(next(self))

    return RVAL

  def _ArrayType(self):
    if next(self) != '{':
      raise ValueError("Invalid dict start token.")

    RVAL = OrderedDict()

    while True:
      kt = next(self)
      if kt == '}':
        break

      if kt not in ('I', 'S'):
        raise ValueError("Dictionary keys must be String or Int, not: %s" % kt)

      # The key should be string or int
      key = self.Value(kt)

      # Get the value type -> pass it to Value() -> Assign to dict
      RVAL[key] = self.Value(next(self))

    return RVAL

  Map = {
    'N' : _NoneType,
    'B' : _BooleanType,
    'I' : _IntType,
    'F' : _FloatType,
    'D' : _DecimalType,
    'S' : _StringType,
    'Y' : _BytesType,
    'T' : _TupleType,
    'L' : _ListType,
    'M' : _DictType,
    'A' : _ArrayType,
    }


###################################################################################################
# Decorators

def WrapFunction(XML):
  def Extruct_FunctionDecorator(fun):
    specs = Parse(XML)
    if len(specs) != 2:
      raise ValueError("Extruct definition must contain exactly 2 top level data definitions.  Input and Output.")
    IN, OUT = specs

    if IN.Name == 'I':
      IN.Name = "{0}.{1}.I".format(fun.__module__, fun.__name__)
    if OUT.Name == 'O':
      OUT.Name = "{0}.{1}.O".format(fun.__module__, fun.__name__)

    wrapper = lambda arg: OUT.Convert(fun(IN.Convert(arg)))
    wrapper.__name__ = "Extruct.WrapFunction around {0}.{1}".format(fun.__module__, fun.__name__)
    return wrapper

  return Extruct_FunctionDecorator


def WrapMethod(XML):
  def Extruct_MethodDecorator(fun):
    specs = Parse(XML)
    if len(specs) != 2:
      raise ValueError("Extruct definition must contain exactly 2 top level data definitions.  Input and Output.")
    IN, OUT = specs

    if IN.Name == 'I':
      IN.Name = "{0}.{1}.I".format(fun.__module__, fun.__name__)
    if OUT.Name == 'O':
      OUT.Name = "{0}.{1}.O".format(fun.__module__, fun.__name__)

    wrapper = lambda arg: OUT.Convert(fun(IN.Convert(arg)))
    wrapper.__name__ = "Extruct.WrapMethod around {0}.{1}.{1}".format(fun.__module__, fun.__class__, fun.__name__)
    return wrapper

  return Extruct_MethodDecorator



def Wrap(XML):
  def Extruct_Decorator(fun):
    specs = Parse('<Extruct>'+XML+'</Extruct>')
    if len(specs) != fun.__code__.co_argcount + 1:
      raise ValueError("Extruct definition for {0}.{1} must contain exactly {2} top level data definitions ({3} args + 1 return)".format(fun.__module__, fun.__name__, fun.__code__.co_argcount+1, fun.__code__.co_argcount))

    for spec in specs:
      spec.Name = "{0}.{1}.{2}".format(fun.__module__, fun.__name__, spec.Name)

    def wrapper(*args):
      return specs[-1].Convert(fun(*(spec.Convert(arg) for spec,arg in zip(specs[:-1],args))))
    
    wrapper.__name__ = "Extruct.Wrap around {0}.{1}".format(fun.__module__, fun.__name__)
    return wrapper

  return Extruct_Decorator


