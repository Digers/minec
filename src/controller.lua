http.request("http://netile.se/minec.php?apkey=m1nK3y&a=wait_event")

local requesting = true
while requesting do
   local event, url, sourceText = os.pullEvent()

   if event == "http_success" then
     local respondedText = sourceText.readAll()
     print(respondedText)
     requesting = false

   elseif event == "http_failure" then
     print("Server didn't respond.")
     requesting = false
   end
 end