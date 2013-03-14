local tArgs = { ... }
if #tArgs < 1 then
  print("Wrong arguments\n")
  print("Usage: bootstrap key")
  return
end

request = http.get("http://netile.se/minec.php?"..
    "apkey=".. tArgs[1] .."&a=get_file&v=stage2")

file = fs.open("stage2", "w")
file.write(request.readAll())
file.close()
request.close()
file = fs.open("keyfile.txt", "w")
file.write(tArgs[1])
file.close()