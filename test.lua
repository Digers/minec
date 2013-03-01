local function level(iLevel)
  print("Setting level "..iLevel)
  if(iLevel == 0) then
    redstone.setOutput("top", false)
    redstone.setBundledOutput("front", 0)
  end
  if(iLevel > 0) then
    redstone.setOutput("top", true)
  end
  if(iLevel == 1) then
    redstone.setBundledOutput("front", colors.red)
  end
  if(iLevel == 2) then
    redstone.setBundledOutput("front", colors.red + colors.orange)
  end
  if(iLevel == 3) then
    redstone.setBundledOutput("front", colors.red + colors.orange + colors.yellow)
  end
  if(iLevel == 4) then
    redstone.setBundledOutput("front", colors.red + colors.orange + colors.yellow + colors.purple)
  end
end

local tArgs = { ... }
if #tArgs < 1 then
  print("Wrong arguments")
  return
end
local sCommand = tArgs[1]
if sCommand == "0" then
  print "Zero"
  level(0)
end
if sCommand == "1" then
  print "one"
  level(1)
end
if sCommand == "2" then
  print "two"
  level(2)
end
if sCommand == "3" then
  print "three"
  level(3)
end
if sCommand == "4" then
  print "four"
  level(4)
end