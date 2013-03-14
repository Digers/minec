os.loadAPI("lib")
local userBus  = "top"
local producerBus = "left"

monitor = peripheral.wrap("bottom")

engineValues = {
    colors.red,
    colors.red + colors.orange,
    colors.red + colors.orange + colors.yellow,
    colors.red + colors.orange + colors.yellow + colors.purple
}

prodInputs = {
    emergency_stop = colors.lightGray
}
prodOutputs = {
    small_valve = colors.white,
    large_valve = colors.blue
}

useInputs = {
    
}

userOutputs = {
    tesseract = colors.green, --?
    workshop  = colors.red --?
}

bitTable = {0, 0}

function level( iLevel )
    resetWithMask(bitTable, 1, engineValues[4])
    if iLevel == 0 then return end
    setWithMask(bitTable, 1, engineValues[iLevel])
end

function resetWithMask(tBitTable, iTable, iBits)
    tBitTable[iTable] = bit.band(tBitTable[iTable], bit.bnot(iBits))
end

function setWithMask(tBitTable, iTable, iBits)
    tBitTable[iTable] = bit.bor(tBitTable[iTable], iBits)
end

function apply()
    redstone.setBundledOutput(producerBus, bitTable[1])
    redstone.setBundledOutput(userBus, bitTable[2])
end

function printStatus(iValue)
    monitor.setCursorPos(1,1)
    monitor.write("Energy value "..iValue)
    monitor.setCursorPos(1,2)
    monitor.write("Outputs OK ")
end

-- this should be prettier
function setLevel(sLevel)
    if sLevel == "0" then
      level(0)
      resetWithMask(bitTable, 2, userOutputs["workshop"])
    end
    if sLevel == "1" then
      level(1)
    end
    if sLevel == "2" then
      level(2)
    end
    if sLevel == "3" then
      level(3)
    end
    if sLevel == "4" then
      level(4)
    end
    apply()
end

-- function setBit(tBitTable, iBit, bValue)
--    if bValue
-- end

--function setEnergyProduction(iLevel)
--    if iLevel == 0 then
--    end
--end

local nuclearI = peripheral.wrap("back")
_uuid,_state,_title,_info = nuclearI.get(1)
print(_state)
print(_title)
print(_info)
print(100*_info["amount"]/_info["capacity"])

-- Setup
os.startTimer(10)
monitor.clear()
setWithMask(bitTable, 1, prodOutputs["small_valve"])
setWithMask(bitTable, 1, prodOutputs["large_valve"])
setWithMask(bitTable, 2, userOutputs["workshop"])
-- Main event machine
while (1) do
    event, aarg = os.pullEvent()
    monitor.clear()
    monitor.setTextColor(colors.white)
    monitor.setCursorPos(1,1)
    monitor.write("Event "..event .. "   ")
    print("Event: ".. event)
    if event == "redstone" then
        -- some level input
        data = redstone.getBundledInput(producerBus)
        if bit.band(data, prodInputs["emergency_stop"]) == prodInputs["emergency_stop"] then
            monitor.clear()
            monitor.setCursorPos(3,2)
            monitor.setTextColor(colors.red)
            monitor.write("EMERGENCY STOP")
            tBitTable[1] = 0
            tBitTable[2] = 0
            apply()
            exit()
        end
        -- check reason
        -- TODO fix
    elseif event == "timer" then
        setLevel(lib.getState("m4st3r", "level"))
        os.startTimer(10)
        -- Request from server
    elseif event == "rednet_message" then
        -- Got event from terinal
    elseif event == "char" then
        setLevel(""..aarg)
        lib.setState("m4st3r", "level", ""..aarg)
    end
end
