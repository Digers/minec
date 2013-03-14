local function fetch_file( sFile )
    local request = http.get("http://netile.se/minec.php?"..
        "apkey=m0n1t0r&a=get_file&v="..sFile)

    local content = request.readAll()
    request.close()
    local file = fs.open(sFile, "w")
    file.write(content)
    file.close()
end

fetch_file("lib")
fetch_file("gui")
fetch_file("main")
