# vim:encoding=utf-8:ts=2:sw=2:expandtab
import Extruct

###############################################################################
@Extruct.Wrap('''
  <Int Name="Num" />
  
  <Int Name="Denom" />
  
  <String Name="return" />
  ''')

def Add(Num, Denom):
  return Num + Denom







class Test:
  
  @Extruct.Wrap('''
    <Object Name="self" />

    <Int Name="Num" />
    
    <Int Name="Denom" />
    
    <String Name="return" />
    ''')

  def Add(self, Num, Denom):
    return Num + Denom

 

print("\n=================================================\n")

print("Add(10,20)")
print( Add(10,20) )

print("\n=================================================\n")

print("X = Test()  ")
print("X.Add(10,20)")
X = Test()
print( X.Add(10,20) )

print("\n=================================================\n")

try:
  print("X = Test()  ")
  print("X.Add('abc',20)")
  X = Test()
  print( X.Add('abc',20) )
except Exception as e:
  print(e)

print("\n=================================================\n")

try:
  print("Going to wrap with invalid arg count")
  @Extruct.Wrap('''
    <Int Name="A" />
    <String Name="return" />
    ''')
  def foo(A, B):
    return str(A+B)
except Exception as e:
  print(e)


print("\n=================================================\n")



