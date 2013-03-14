os.loadAPI("gui")
gui.clear()
gui.heading("Hello world")

function testbutton()
    print("It works!")
end
gui.label(2,2,"Level")
gui.setTable("One", testbutton,  10, 25, 3, 5)
gui.setTable("Two", testbutton,  10, 25, 7, 10)
gui.screen()