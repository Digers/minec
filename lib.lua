function setState(sState, sValue)
    result = http.get("http://netile.se/minec.php?apkey=m1nK3y&".."a=set&k="..sState.."&v="..sValue)
    result.close()
end

function getState(sState)
    r = http.get("http://netile.se/minec.php?apkey=m1nK3y&".."a=get&k="..sState)
    v = r.readAll()
    r.close()
    return v
end
