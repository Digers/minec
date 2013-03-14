function setState(key, sState, sValue)
    result = http.get("http://netile.se/minec.php?apkey="..key.."&".."a=set&k="..sState.."&v="..sValue)
    result.close()
end

function getState(key, sState)
    r = http.get("http://netile.se/minec.php?apkey="..key.."&".."a=get&k="..sState)
    v = r.readAll()
    r.close()
    return v
end
