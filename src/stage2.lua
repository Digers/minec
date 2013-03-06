local function fetch_file( sFile )
    local request = http.get("http://netile.se/minec.php?"..
        "apkey=m1nK3y&a=get_file&v="..sFile)

    local content = request.readAll()
    request.close()
    local file = fs.open(sFile, "w")
    file.write(content)
    file.close()
end

fetch_file("lib")
fetch_file("controller")
fetch_file("main")
