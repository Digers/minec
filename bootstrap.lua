local function function_name( sFile )
    local request = http.get("http://netile.se/minec.php?"..
        "apkey=m1nK3y&a=get_file&v="..sFile)

    content = f.readAll()
    f.close()
    file = fs.open("filename", "w")
    file.write(content)
    file.close()

end

